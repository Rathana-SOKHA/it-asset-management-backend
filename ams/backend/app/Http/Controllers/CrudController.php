<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

/** Generic CRUD for the simple master-data resources. */
class CrudController extends Controller {
    private function map(): array {
        return [
            'employees'    => [\App\Models\Employee::class, ['name' => 'required', 'email' => 'required|email', 'department_id' => 'nullable|exists:departments,id', 'position' => 'nullable', 'status' => 'nullable'], ['department']],
            'departments'  => [\App\Models\Department::class, ['name' => 'required'], []],
            'locations'    => [\App\Models\Location::class, ['parent_id' => 'nullable|exists:locations,id', 'type' => 'required|in:office,building,floor,room', 'name' => 'required'], ['parent']],
            'categories'   => [\App\Models\Category::class, ['name' => 'required'], []],
            'warranties'   => [\App\Models\Warranty::class, ['asset_id' => 'required|exists:assets,id', 'vendor' => 'nullable', 'start_date' => 'required|date', 'end_date' => 'required|date|after:start_date'], ['asset']],
            'maintenances' => [\App\Models\Maintenance::class, ['asset_id' => 'required|exists:assets,id', 'type' => 'required|in:repair,scheduled', 'description' => 'nullable', 'cost' => 'nullable|numeric', 'scheduled_at' => 'nullable|date', 'completed_at' => 'nullable|date', 'performed_by' => 'nullable|exists:users,id'], ['asset']],
        ];
    }
    private function cfg(Request $r): array { return $this->map()[$r->route('resource')] ?? abort(404); }
    public function index(Request $r) { [$m, , $with] = $this->cfg($r); return $m::with($with)->latest()->paginate(50); }
    public function store(Request $r) { [$m, $rules] = $this->cfg($r); return response()->json($m::create($r->validate($rules)), 201); }
    public function update(Request $r, $id) {
        [$m, $rules] = $this->cfg($r); $o = $m::findOrFail($id);
        $o->update($r->validate(array_map(fn($x) => 'sometimes|' . $x, $rules))); return $o;
    }
    public function destroy(Request $r, $id) { [$m] = $this->cfg($r); $m::findOrFail($id)->delete(); return response()->noContent(); }
}
