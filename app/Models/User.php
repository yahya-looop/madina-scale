<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Application user model.
 *
 * This model is responsible for:
 * - Authentication
 * - User profile data
 * - Account status
 * - Login tracking state
 * - Password security state
 * - Role relationships
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string $status
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BelongsToMany<Role, $this> $roles
 */
#[Fillable([
    'name',
    'email',
    'password',
    'status',
    'last_login_at',
    'last_login_ip',
    'failed_login_attempts',
    'locked_until',
    'password_changed_at',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the roles assigned to this user.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id',
        );
    }

    /** Locations this user is explicitly assigned to. */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_user', 'user_id', 'location_id')
            ->withPivot(['access_level', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * Date/time fields are automatically converted to Carbon
     * instances and the password is automatically hashed when set.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

            // Login tracking
            'last_login_at' => 'datetime',

            // Authentication security state
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }
}
