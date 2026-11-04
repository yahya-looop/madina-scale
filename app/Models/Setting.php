<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
