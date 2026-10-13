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
        $users = User::query()
            ->with([
                'roles:id,name,slug,is_system,is_active',
            ])
            ->orderBy('name')
            ->get();

        return $users->each(
            fn (User $user): User => $this->prepareForManagement($user),
        );
    }

    /**
     * Find a user by primary key.
     */
    public function find(int $id): User
    {
        $user = User::query()
            ->with([
                'roles:id,name,slug,is_system,is_active',
            ])
            ->findOrFail($id);

        return $this->prepareForManagement($user);
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
            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'status' => $attributes['status'] ?? 'active',
                'failed_login_attempts' => 0,
                'password_changed_at' => now(),
            ]);

            $user->load([
                'roles:id,name,slug,is_system,is_active',
            ]);

            return $this->prepareForManagement($user);
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

            $user = $user
                ->refresh()
                ->load([
                    'roles:id,name,slug,is_system,is_active',
                ]);

            return $this->prepareForManagement($user);
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
            /*
             * Normalize role IDs before validation.
             *
             * This prevents duplicate IDs from creating an inconsistent
             * role count comparison.
             */
            $normalizedRoleIds = array_values(
                array_unique(
                    array_map(
                        static fn (mixed $roleId): int => (int) $roleId,
                        $roleIds,
                    ),
                ),
            );

            $roles = Role::query()
                ->whereIn('id', $normalizedRoleIds)
                ->get();

            /*
             * Every requested role must exist.
             */
            if ($roles->count() !== count($normalizedRoleIds)) {
                abort(
                    422,
                    'One or more selected roles do not exist.',
                );
            }

            /*
             * System roles cannot be assigned through normal
             * user management.
             */
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

            /*
             * Inactive roles cannot be assigned.
             */
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

            /*
             * Preserve existing system roles during ordinary user
             * management. System roles are intentionally not assignable
             * from the user form, but editing a user's name/email/status
             * must never silently remove their existing system access.
             *
             * Only active system roles already attached to this user are
             * preserved; this method still cannot grant a system role.
             */
            $existingSystemRoleIds = $user->roles()
                ->where('roles.is_system', true)
                ->pluck('roles.id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $roleIdsToSync = array_values(array_unique([
                ...$existingSystemRoleIds,
                ...$normalizedRoleIds,
            ]));

            $user->roles()->sync($roleIdsToSync);

            $user = $user
                ->refresh()
                ->load([
                    'roles:id,name,slug,is_system,is_active',
                ]);

            return $this->prepareForManagement($user);
        });
    }

    /**
     * Change the user's application status.
     */
    public function setStatus(
        User $user,
        string $status,
    ): User {
        return DB::transaction(function () use (
            $user,
            $status,
        ): User {
            $user->update([
                'status' => $status,
            ]);

            $user = $user
                ->refresh()
                ->load([
                    'roles:id,name,slug,is_system,is_active',
                ]);

            return $this->prepareForManagement($user);
        });
    }

    /**
     * Prepare a user model for the user-management response.
     *
     * The actual login timestamp is stored in `last_login_at`.
     *
     * The frontend Users/Index.vue can consume the camelCase
     * `lastLoginAt` property while the original database-backed
     * `last_login_at` attribute remains untouched.
     */
    private function prepareForManagement(User $user): User
    {
        $lastLoginAt = $user->getAttribute('last_login_at');

        /*
         * Keep the original database attribute intact and additionally
         * expose the frontend-friendly property.
         */
        $user->setAttribute(
            'lastLoginAt',
            $lastLoginAt,
        );

        return $user;
    }
}
