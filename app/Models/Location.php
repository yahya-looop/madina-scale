<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property-read LocationUser $pivot
 * @property string $name
 * @property string $code
 * @property string $type
 * @property bool $is_active
 * @property bool $is_primary
 * @property array<string, mixed>|null $business_hours
 * @property array<string, mixed>|null $metadata
 */
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

    /** Users explicitly assigned to this location. */
    /** @return BelongsToMany<User, $this, LocationUser> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_user', 'location_id', 'user_id')
            ->using(LocationUser::class)
            ->withPivot(['access_level', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * Scope active locations.
     */
    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope primary locations.
     */
    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope locations by type.
     */
    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
