<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'expected_return_at' => 'date',
            'returned_at' => 'datetime',
            'handover_confirmed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(AssetReturn::class);
    }
}
