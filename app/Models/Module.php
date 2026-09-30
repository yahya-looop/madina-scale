<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_system
 * @property bool $is_active
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'icon',
    'sort_order',
    'is_system',
    'is_active',
])]
class Module extends Model
{
    /** @use HasFactory */
    use HasFactory;

    /**
     * Get the permissions belonging to this module.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
