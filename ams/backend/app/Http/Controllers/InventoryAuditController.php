<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryAuditController extends Controller
{
    public function index()
    {
        return InventoryAudit::with('location', 'starter:id,name', 'items')
            ->latest()
            ->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'audit_number' => 'required|string|max:255|unique:inventory_audits,audit_number',
            'location_id' => 'nullable|integer|exists:locations,id',
            'notes' => 'nullable|string',
        ]);
        $data['started_by'] = $request->user()->id;
        $data['started_at'] = now();
        $data['status'] = 'in_progress';

        return response()->json(InventoryAudit::create($data)->load('location', 'starter:id,name'), 201);
    }

    public function show(InventoryAudit $inventoryAudit)
    {
        return $inventoryAudit->load('location', 'starter:id,name', 'items.asset', 'items.verifier:id,name', 'items.actualLocation');
    }

    public function update(Request $request, InventoryAudit $inventoryAudit)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'notes' => 'sometimes|nullable|string',
        ]);
        if ($data['status'] === 'completed') {
            $data['finished_at'] = now();
        }
        $inventoryAudit->update($data);

        return $inventoryAudit->fresh()->load('location', 'starter:id,name', 'items');
    }

    public function destroy(InventoryAudit $inventoryAudit)
    {
        $inventoryAudit->delete();

        return response()->noContent();
    }

    public function addItems(Request $request, InventoryAudit $inventoryAudit)
    {
        $assetIds = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'required|integer|distinct|exists:assets,id',
        ])['asset_ids'];

        $assets = Asset::query()->whereIn('id', $assetIds)->get();
        if ($inventoryAudit->location_id !== null && $assets->contains(fn (Asset $asset): bool => $asset->location_id !== $inventoryAudit->location_id)) {
            return response()->json(['message' => 'All audited assets must be assigned to the audit location.'], 422);
        }

        DB::transaction(function () use ($inventoryAudit, $assetIds): void {
            foreach ($assetIds as $assetId) {
                $inventoryAudit->items()->firstOrCreate(['asset_id' => $assetId]);
            }
        });

        return response()->json($inventoryAudit->fresh()->load('items.asset'), 201);
    }

    public function verifyItem(Request $request, InventoryAuditItem $inventoryAuditItem)
    {
        $data = $request->validate([
            'status' => 'required|in:verified,missing,damaged',
            'actual_location_id' => 'nullable|integer|exists:locations,id',
            'condition' => 'nullable|in:good,fair,poor,damaged,unknown',
            'notes' => 'nullable|string',
        ]);
        $inventoryAuditItem->update($data + [
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return $inventoryAuditItem->fresh()->load('asset', 'verifier:id,name', 'actualLocation');
    }
}
