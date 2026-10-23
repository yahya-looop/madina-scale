<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'customer_code', 'name', 'phone', 'email', 'company_name',
        'address_line', 'area', 'district', 'division', 'postal_code',
        'notes', 'status',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') return $query;
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        return $query->where(function (Builder $q) use ($escaped): void {
            $q->where('customer_code', 'like', "%{$escaped}%")
              ->orWhere('name', 'like', "%{$escaped}%")
              ->orWhere('phone', 'like', "%{$escaped}%")
              ->orWhere('email', 'like', "%{$escaped}%")
              ->orWhere('company_name', 'like', "%{$escaped}%");
        });
    }
}
