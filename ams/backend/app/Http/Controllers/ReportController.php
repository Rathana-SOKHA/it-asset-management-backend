<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;

class ReportController extends Controller
{
    public function assetsCsv(Request $request): StreamedResponse
    {
        $assets = Asset::query();
        if ($request->user()->hasRole('Department Manager')) {
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

        return response()->streamDownload(function () use ($assets): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Asset tag', 'Name', 'Status', 'Category', 'Brand', 'Serial number', 'Location', 'Supplier', 'Purchase date', 'Purchase cost']);

            $assets->with('category', 'brand', 'location', 'supplier')->orderBy('asset_tag')->chunk(500, function ($assetRows) use ($stream): void {
                foreach ($assetRows as $asset) {
                    fputcsv($stream, [
                        $asset->asset_tag,
                        $asset->name,
                        $asset->status,
                        $asset->category?->name,
                        $asset->brand?->name ?? $asset->brand,
                        $asset->serial_no,
                        $asset->location?->name,
                        $asset->supplier?->name,
                        $asset->purchase_date,
                        $asset->purchase_cost,
                    ]);
                }
            });

            fclose($stream);
        }, 'assets-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
