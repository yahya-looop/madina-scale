<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierRateRule extends Model
{
    protected $fillable = ['courier_provider_id','zone_code','min_weight_grams','max_weight_grams','base_charge','additional_per_kg','cod_charge_percent','is_enabled','sort_order'];
    protected function casts(): array { return ['min_weight_grams'=>'integer','max_weight_grams'=>'integer','base_charge'=>'decimal:2','additional_per_kg'=>'decimal:2','cod_charge_percent'=>'decimal:3','is_enabled'=>'boolean','sort_order'=>'integer']; }
    public function provider(): BelongsTo { return $this->belongsTo(CourierProvider::class, 'courier_provider_id'); }
}
