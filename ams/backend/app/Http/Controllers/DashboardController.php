<?php
namespace App\Http\Controllers;
use App\Models\{Asset, Category, Maintenance, Warranty};

class DashboardController extends Controller {
    public function stats() {
        return [
            'total'    => Asset::count(),
            'active'   => Asset::where('status', 'active')->count(),
            'damaged'  => Asset::where('status', 'damaged')->count(),
            'assigned' => Asset::whereHas('currentAssignment')->count(),
            'by_status'   => Asset::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'by_category' => Category::withCount('assets')->get()->pluck('assets_count', 'name'),
            'warranty_expiring' => Warranty::with('asset:id,name,asset_tag')->whereBetween('end_date', [now(), now()->addDays(30)])->get(),
            'maintenance_due'   => Maintenance::with('asset:id,name,asset_tag')->whereNull('completed_at')->whereBetween('scheduled_at', [now(), now()->addDays(7)])->get(),
        ];
    }
}
