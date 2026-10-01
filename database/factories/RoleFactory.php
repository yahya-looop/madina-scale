<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * The model that this factory corresponds to.
     *
     * @var class-string<Role>
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'description' => fake()->optional()->sentence(),
            'is_system' => false,
            'is_active' => true,
        ];
    }

    /**
     * Create an inactive role.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a system role.
     */
    public function system(): static
    {
        return $this->state(fn (): array => [
            'is_system' => true,
        ]);
    }
}
