<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's system configuration.
     *
     * Privileged user accounts must be created through a deliberate,
     * secure bootstrap process. Never seed a predictable Developer login.
     */
    public function run(): void
    {
        $this->call([
            SystemAuthorizationSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
