<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model {
    protected $guarded = [];
    public function category() { return $this->belongsTo(Category::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function warranty() { return $this->hasOne(Warranty::class)->latestOfMany('end_date'); }
    public function maintenances() { return $this->hasMany(Maintenance::class); }
    public function assignments() { return $this->hasMany(AssetAssignment::class, 'asset_id'); }
    public function currentAssignment() { return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latestOfMany('assigned_at'); }
}
