<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/** Generic CRUD for the simple master-data resources. */
class CrudController extends Controller
{
    private function map(): array
    {
        return [
            'employees' => [Employee::class, [
                'employee_number' => ['nullable', 'string', 'max:100', Rule::unique('employees', 'employee_number')],
                'name' => 'required|string|max:255',
                'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')],
                'phone' => 'nullable|string|max:50',
                'department_id' => 'nullable|exists:departments,id',
                'position' => 'nullable|string|max:255',
                'status' => 'sometimes|in:active,inactive,terminated',
            ], ['department', 'assetAssignments']],
            'departments' => [Department::class, [
                'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')],
                'manager_id' => 'nullable|exists:users,id',
            ], ['manager']],
            'locations' => [Location::class, [
                'parent_id' => 'nullable|exists:locations,id',
                'type' => 'required|in:office,building,floor,room',
                'name' => 'required|string|max:255',
            ], ['parent', 'children', 'assets']],
            'categories' => [Category::class, [
                'name' => ['required', 'string', 'max:255', Rule::unique('asset_categories', 'name')],
            ], ['assets']],
            'brands' => [Brand::class, [
                'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')],
            ], ['assets']],
            'suppliers' => [Supplier::class, [
                'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')],
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string',
                'status' => 'sometimes|in:active,inactive',
            ], ['assets', 'purchases']],
            'purchases' => [Purchase::class, [
                'supplier_id' => 'nullable|exists:suppliers,id',
                'purchase_order_number' => ['nullable', 'string', 'max:255', Rule::unique('purchases', 'purchase_order_number')],
                'purchase_date' => 'nullable|date',
                'total_amount' => 'nullable|numeric|min:0',
                'status' => 'sometimes|in:ordered,received,cancelled',
                'notes' => 'nullable|string',
            ], ['supplier', 'assets']],
            'warranties' => [Warranty::class, [
                'asset_id' => 'required|exists:assets,id',
                'supplier_id' => 'nullable|exists:suppliers,id',
                'vendor' => 'nullable|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
            ], ['asset', 'supplier']],
            'maintenances' => [Maintenance::class, [
                'asset_id' => 'required|exists:assets,id',
                'type' => 'required|in:repair,scheduled',
                'status' => 'sometimes|in:reported,pending,in_progress,completed,cancelled',
                'description' => 'nullable|string',
                'cost' => 'nullable|numeric|min:0',
                'scheduled_at' => 'nullable|date',
                'completed_at' => 'nullable|date',
                'performed_by' => 'nullable|exists:users,id',
            ], ['asset', 'performer']],
        ];
    }

    private function cfg(Request $r): array
    {
        $resource = $r->route('resource');
        $configuration = $this->map()[$resource] ?? abort(404);
        $read = $r->isMethod('GET');
        $permission = match ($resource) {
            'employees' => $read ? 'employees.view' : 'employees.manage',
            'departments' => $read ? 'departments.view' : 'departments.manage',
            'locations' => $read ? 'locations.view' : 'locations.manage',
            'categories', 'brands' => $read ? 'assets.catalog.view' : 'assets.update',
            'suppliers' => $read ? 'suppliers.view' : 'suppliers.manage',
            'purchases' => $read ? 'purchases.view' : 'purchases.manage',
            'warranties' => $read ? 'warranties.view' : 'warranties.manage',
            'maintenances' => 'maintenance.manage',
            default => null,
        };

        abort_if($permission !== null && ! $r->user()->can($permission), 403);

        return $configuration;
    }

    public function index(Request $r)
    {
        [$model,, $with] = $this->cfg($r);
        $query = $model::with($with);
        if ($r->user()->hasRole('Department Manager')) {
            $departmentIds = Department::query()->where('manager_id', $r->user()->id)->pluck('id');
            if ($r->user()->employee?->department_id !== null) {
                $departmentIds->push($r->user()->employee->department_id);
            }
            if ($model === Department::class) {
                $query->whereIn('id', $departmentIds);
            } elseif ($model === Employee::class) {
                $query->whereIn('department_id', $departmentIds);
            }
        }

        return $query->latest()->paginate(50);
    }

    public function store(Request $r)
    {
        [$m, $rules] = $this->cfg($r);

        return response()->json($m::create($r->validate($rules)), 201);
    }

    public function update(Request $r, $id)
    {
        [$m, $rules] = $this->cfg($r);
        $o = $m::findOrFail($id);
        $updateRules = [];
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            foreach ($fieldRules as $rule) {
                if ($rule instanceof Unique) {
                    $rule->ignore($o->getKey());
                }
            }
            $updateRules[$field] = array_merge(['sometimes'], $fieldRules);
        }

        $o->update($r->validate($updateRules));

        return $o;
    }

    public function destroy(Request $r, $id)
    {
        [$m] = $this->cfg($r);
        $m::findOrFail($id)->delete();

        return response()->noContent();
    }
}
