<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryReservationPurpose extends Model
{
    protected $fillable = ['key', 'label', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
