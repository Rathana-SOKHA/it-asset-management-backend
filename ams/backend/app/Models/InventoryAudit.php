<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAudit extends Model
{
    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAuditItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function finish(): void
    {
        $this->update(['status' => 'completed', 'finished_at' => now()]);
    }
}
