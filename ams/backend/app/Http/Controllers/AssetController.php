<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Department;
use App\Models\User;
use App\Notifications\AssetAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function index(Request $r)
    {
        $filters = $r->validate([
            'status' => 'nullable|in:active,damaged,in_repair,retired',
            'category_id' => 'nullable|integer|exists:asset_categories,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'search' => 'nullable|string|max:120',
        ]);

        return $this->scopeToUser(Asset::query(), $r)
            ->with('category', 'location', 'warranty', 'currentAssignment.employee', 'currentAssignment.department')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['location_id'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")
                ->orWhere('asset_tag', 'like', "%$v%")
                ->orWhere('serial_no', 'like', "%$v%")
                ->orWhere('model', 'like', "%$v%")))
            ->latest()->paginate(20);
    }

    public function show(Request $request, Asset $asset)
    {
        return $this->scopeToUser(Asset::query(), $request)
            ->with('category', 'location', 'brand', 'supplier', 'purchase', 'warranty', 'maintenances', 'assignments.employee', 'assignments.department')
            ->findOrFail($asset->id);
    }

    private function scopeToUser(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if (! $user->hasRole('Department Manager')) {
            return $query;
        }

        $departmentIds = Department::query()->where('manager_id', $user->id)->pluck('id');
        $employeeDepartmentId = $user->employee?->department_id;
        if ($employeeDepartmentId !== null) {
            $departmentIds->push($employeeDepartmentId);
        }

        return $query->whereHas('currentAssignment', function (Builder $assignmentQuery) use ($departmentIds): void {
            $assignmentQuery->whereIn('department_id', $departmentIds)
                ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->whereIn('department_id', $departmentIds));
        });
    }

    private function rules(): array
    {
        return [
            'asset_tag' => 'required|unique:assets,asset_tag',
            'name' => 'required',
            'category_id' => 'required|exists:asset_categories,id',
            'brand' => 'nullable',
            'brand_id' => 'nullable|exists:brands,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'model' => 'nullable',
            'serial_no' => 'nullable',
            'status' => 'in:active,damaged,in_repair,retired',
            'location_id' => 'nullable|exists:locations,id',
            'technician_id' => 'nullable|exists:users,id',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric',
        ];
    }

    public function store(Request $r)
    {
        return response()->json(Asset::create($r->validate($this->rules())), 201);
    }

    public function update(Request $r, Asset $asset)
    {
        $u = $r->user();
        // IT Staff may only update assets they are the technician for
        abort_if($u->hasRole('IT Staff') && $asset->technician_id !== $u->id, 403, 'Not your asset');
        $rules = $this->rules();
        $rules['asset_tag'] .= ','.$asset->id;
        $rules = array_map(fn ($x) => 'sometimes|'.$x, $rules);
        $asset->update($r->validate($rules));

        return $asset->fresh();
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return response()->noContent();
    }

    public function assign(Request $r, Asset $asset)
    {
        $d = $r->validate([
            'employee_id' => 'nullable|required_without:department_id|prohibited_with:department_id|exists:employees,id',
            'department_id' => 'nullable|required_without:employee_id|prohibited_with:employee_id|exists:departments,id',
            'expected_return_at' => 'nullable|date|after_or_equal:today',
            'handover_confirmed' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);
        $handoverConfirmed = (bool) ($d['handover_confirmed'] ?? false);
        unset($d['handover_confirmed']);
        $assignment = DB::transaction(function () use ($asset, $d, $r, $handoverConfirmed) {
            Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $returnedAt = now();
            $activeAssignments = $asset->assignments()->whereNull('returned_at')->lockForUpdate()->get();

            foreach ($activeAssignments as $activeAssignment) {
                $activeAssignment->update(['returned_at' => $returnedAt]);
                $activeAssignment->returns()->create([
                    'asset_id' => $asset->id,
                    'received_by' => $r->user()->id,
                    'returned_at' => $returnedAt,
                    'notes' => 'Closed as part of an asset transfer.',
                ]);
            }

            return $asset->assignments()->create([
                ...$d,
                'assigned_at' => $returnedAt,
                'handover_confirmed_at' => $handoverConfirmed ? $returnedAt : null,
            ]);
        });

        $recipientUsers = $assignment->employee?->user;
        if ($recipientUsers !== null) {
            $recipientUsers->notify(new AssetAlert("Asset {$asset->asset_tag} was assigned to you."));
        } elseif ($assignment->department_id !== null) {
            User::whereHas('employee', fn ($query) => $query->where('department_id', $assignment->department_id))
                ->get()
                ->each(fn (User $user) => $user->notify(new AssetAlert("Asset {$asset->asset_tag} was assigned to your department.")));
        }

        return response()->json($assignment->load('employee', 'department'), 201);
    }

    public function return(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'condition' => 'nullable|in:good,fair,poor,damaged',
            'notes' => 'nullable|string',
        ]);
        $assetReturn = DB::transaction(function () use ($request, $asset, $data) {
            Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $assignment = $asset->assignments()->whereNull('returned_at')->latest('assigned_at')->lockForUpdate()->first();
            if ($assignment === null) {
                return null;
            }

            $returnedAt = now();
            $assignment->update(['returned_at' => $returnedAt]);

            return $assignment->returns()->create([
                'asset_id' => $asset->id,
                'received_by' => $request->user()->id,
                'returned_at' => $returnedAt,
                'condition' => $data['condition'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        if ($assetReturn === null) {
            return response()->json(['message' => 'Asset has no active assignment.'], 422);
        }

        $recipient = $assetReturn->assignment()->with('employee.user')->first()?->employee?->user;
        $recipient?->notify(new AssetAlert("Asset {$asset->asset_tag} was returned to IT inventory."));

        return response()->json(['ok' => true, 'return' => $assetReturn]);
    }

    public function mine(Request $r)
    {
        $e = $r->user()->employee_id;

        return Asset::with('category', 'warranty')->whereHas('currentAssignment', fn ($q) => $q->where('employee_id', $e))->get();
    }
}
