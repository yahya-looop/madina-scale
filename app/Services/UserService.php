<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    /**
     * Get users available for management.
     *
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::query()
            ->with([
                'roles:id,name,slug,is_system,is_active',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a user by primary key.
     */
    public function find(int $id): User
    {
        return User::query()
            ->with([
                'roles:id,name,slug,is_system,is_active',
            ])
            ->findOrFail($id);
    }

    /**
     * Create a user.
     *
     * Password hashing is handled by the User model's hashed cast.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     status?: string
     * } $attributes
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            return User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'status' => $attributes['status'] ?? 'active',
                'failed_login_attempts' => 0,
                'password_changed_at' => now(),
            ]);
        });
    }

    /**
     * Update a user.
     *
     * Password-related state is updated only when a new password
     * is explicitly supplied.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password?: string|null,
     *     status?: string
     * } $attributes
     */
    public function update(User $user, array $attributes): User
    {
        return DB::transaction(function () use (
            $user,
            $attributes,
        ): User {
            $data = [
                'name' => $attributes['name'],
                'email' => $attributes['email'],
            ];

            if (array_key_exists('status', $attributes)) {
                $data['status'] = $attributes['status'];
            }

            if (
                array_key_exists('password', $attributes)
                && $attributes['password'] !== null
                && $attributes['password'] !== ''
            ) {
                $data['password'] = $attributes['password'];
                $data['password_changed_at'] = now();
                $data['failed_login_attempts'] = 0;
                $data['locked_until'] = null;
            }

            $user->update($data);

            return $user->refresh();
        });
    }

    /**
     * Synchronize roles assigned to a user.
     *
     * System roles cannot be assigned through normal user management.
     *
     * @param array<int, int> $roleIds
     */
    public function syncRoles(
        User $user,
        array $roleIds,
    ): User {
        return DB::transaction(function () use (
            $user,
            $roleIds,
        ): User {
            $roles = Role::query()
                ->whereIn('id', $roleIds)
                ->get();

            if ($roles->count() !== count(array_unique($roleIds))) {
                abort(422, 'One or more selected roles do not exist.');
            }

            if (
                $roles->contains(
                    static fn (Role $role): bool => $role->is_system
                )
            ) {
                abort(
                    403,
                    'System roles cannot be assigned through user management.',
                );
            }

            if (
                $roles->contains(
                    static fn (Role $role): bool => ! $role->is_active
                )
            ) {
                abort(
                    422,
                    'Inactive roles cannot be assigned to a user.',
                );
            }

            $user->roles()->sync($roleIds);

            return $user->refresh()->load([
                'roles:id,name,slug,is_system,is_active',
            ]);
        });
    }

    /**
     * Change the user's application status.
     */
    public function setStatus(User $user, string $status): User
    {
        return DB::transaction(function () use (
            $user,
            $status,
        ): User {
            $user->update([
                'status' => $status,
            ]);

            return $user->refresh();
        });
    }
}
