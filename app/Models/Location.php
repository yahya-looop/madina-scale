<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'is_active',
        'is_primary',
        'country_code',
        'division',
        'district',
        'postal_code',
        'address',
        'phone',
        'email',
        'business_hours',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
            'business_hours' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Scope active locations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope primary locations.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope locations by type.
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}