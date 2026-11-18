<?php
namespace App\Http\Controllers;
use App\Models\Asset;
use Illuminate\Http\Request;

class AssetController extends Controller {
    public function index(Request $r) {
        return Asset::with('category', 'location', 'warranty', 'currentAssignment.employee', 'currentAssignment.department')
            ->when($r->status, fn($q, $v) => $q->where('status', $v))
            ->when($r->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($r->search, fn($q, $v) => $q->where(fn($w) => $w->where('name', 'like', "%$v%")->orWhere('asset_tag', 'like', "%$v%")->orWhere('serial_no', 'like', "%$v%")))
            ->latest()->paginate(20);
    }
    public function show(Asset $asset) {
        return $asset->load('category', 'location', 'warranty', 'maintenances', 'assignments.employee', 'assignments.department');
    }
    private function rules(): array {
        return ['asset_tag' => 'required|unique:assets,asset_tag', 'name' => 'required', 'category_id' => 'required|exists:categories,id',
            'brand' => 'nullable', 'model' => 'nullable', 'serial_no' => 'nullable', 'status' => 'in:active,damaged,in_repair,retired',
            'location_id' => 'nullable|exists:locations,id', 'technician_id' => 'nullable|exists:users,id',
            'purchase_date' => 'nullable|date', 'purchase_cost' => 'nullable|numeric'];
    }
    public function store(Request $r) { return response()->json(Asset::create($r->validate($this->rules())), 201); }
    public function update(Request $r, Asset $asset) {
        $u = $r->user();
        // IT Staff may only update assets they are the technician for
        abort_if($u->hasRole('IT Staff') && $asset->technician_id !== $u->id, 403, 'Not your asset');
        $rules = $this->rules();
        $rules['asset_tag'] .= ',' . $asset->id;
        $rules = array_map(fn($x) => 'sometimes|' . $x, $rules);
        $asset->update($r->validate($rules));
        return $asset->fresh();
    }
    public function destroy(Asset $asset) { $asset->delete(); return response()->noContent(); }

    public function assign(Request $r, Asset $asset) {
        $d = $r->validate(['employee_id' => 'required_without:department_id|exists:employees,id',
            'department_id' => 'required_without:employee_id|exists:departments,id', 'notes' => 'nullable']);
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        return response()->json($asset->assignments()->create($d + ['assigned_at' => now()]), 201);
    }
    public function return(Asset $asset) {
        $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
        return response()->json(['ok' => true]);
    }
    public function mine(Request $r) {
        $e = $r->user()->employee_id;
        return Asset::with('category', 'warranty')->whereHas('currentAssignment', fn($q) => $q->where('employee_id', $e))->get();
    }
}
