<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAuditItem extends Model
{
    protected $guarded = [];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InventoryAudit::class, 'inventory_audit_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function actualLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'actual_location_id');
    }
}
