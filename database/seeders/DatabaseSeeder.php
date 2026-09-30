<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $developerRole = Role::updateOrCreate(
            ['slug' => 'developer'],
            [
                'name' => 'Developer',
                'description' => 'System-level developer role with highest administrative trust.',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ],
        );

        $user->roles()->syncWithoutDetaching([
            $developerRole->id,
        ]);
    }
}
