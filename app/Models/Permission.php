<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $module_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
#[Fillable([
    'module_id',
    'name',
    'slug',
    'description',
])]
class Permission extends Model
{
    /** @use HasFactory */
    use HasFactory;

    /**
     * Get the module that owns this permission.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the roles assigned to this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        );
    }
}
