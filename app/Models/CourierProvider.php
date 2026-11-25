<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierProvider extends Model
{
    protected $fillable = ['code','name','description','logo_url','is_enabled','priority','environment','capabilities','service_zones','credentials','settings','last_health_check_at','health_status'];
    protected function casts(): array
    {
        return ['is_enabled'=>'boolean','priority'=>'integer','capabilities'=>'array','service_zones'=>'array','credentials'=>'encrypted:array','settings'=>'array','last_health_check_at'=>'datetime'];
    }
    public function rates(): HasMany { return $this->hasMany(CourierRateRule::class, 'courier_provider_id'); }
}
