<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Department;
use App\Models\Maintenance;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $assets = Asset::query();
        $departmentManager = $request->user()->hasRole('Department Manager');
        if ($departmentManager) {
            $departmentIds = Department::query()->where('manager_id', $request->user()->id)->pluck('id');
            $employeeDepartmentId = $request->user()->employee?->department_id;
            if ($employeeDepartmentId !== null) {
                $departmentIds->push($employeeDepartmentId);
            }
            $assets->whereHas('currentAssignment', function (Builder $query) use ($departmentIds): void {
                $query->whereIn('department_id', $departmentIds)
                    ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->whereIn('department_id', $departmentIds));
            });
        }

        return [
            'total' => (clone $assets)->count(),
            'available' => (clone $assets)->where('status', 'active')->whereDoesntHave('currentAssignment')->count(),
            'assigned' => (clone $assets)->whereHas('currentAssignment')->count(),
            'under_maintenance' => (clone $assets)->where('status', 'in_repair')->count(),
            'damaged' => (clone $assets)->where('status', 'damaged')->count(),
            'disposed' => (clone $assets)->where('status', 'retired')->count(),
            'by_status' => (clone $assets)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'by_category' => (clone $assets)->join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
                ->selectRaw('asset_categories.name, count(assets.id) as total')->groupBy('asset_categories.name')->pluck('total', 'name'),
            'by_location' => (clone $assets)->join('locations', 'assets.location_id', '=', 'locations.id')
                ->selectRaw('locations.name, count(assets.id) as total')->groupBy('locations.name')->pluck('total', 'name'),
            'by_department' => Department::query()
                ->when($departmentManager, fn ($query) => $query->where('manager_id', $request->user()->id)
                    ->orWhereIn('id', $request->user()->employee?->department_id ? [$request->user()->employee->department_id] : []))
                ->get()->mapWithKeys(function (Department $department) use ($assets): array {
                    $count = (clone $assets)->whereHas('currentAssignment', function ($query) use ($department): void {
                        $query->where('department_id', $department->id)
                            ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $department->id));
                    })->count();

                    return [$department->name => $count];
                }),
            'warranty_expiring' => Warranty::with('asset:id,name,asset_tag')
                ->whereHas('asset', fn (Builder $query) => $query->whereIn('assets.id', (clone $assets)->select('assets.id')))
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->get(),
            'maintenance_due' => Maintenance::with('asset:id,name,asset_tag')
                ->whereHas('asset', fn (Builder $query) => $query->whereIn('assets.id', (clone $assets)->select('assets.id')))
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereBetween('scheduled_at', [now()->toDateString(), now()->addDays(7)->toDateString()])->get(),
            'maintenance_cost_30_days' => Maintenance::where('status', 'completed')
                ->whereBetween('completed_at', [now()->subDays(30)->toDateString(), now()->toDateString()])->sum('cost'),
            'recent_activities' => ActivityLog::with('user:id,name')
                ->when($departmentManager, fn ($query) => $query->where('user_id', $request->user()->id))
                ->latest()->limit(10)->get(),
        ];
    }
}
