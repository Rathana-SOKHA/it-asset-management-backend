<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class)->latestOfMany('end_date');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function maintenances(): HasMany
    {
        return $this->maintenanceRecords();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latestOfMany('assigned_at');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(AssetReturn::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function inventoryAuditItems(): HasMany
    {
        return $this->hasMany(InventoryAuditItem::class);
    }
}
