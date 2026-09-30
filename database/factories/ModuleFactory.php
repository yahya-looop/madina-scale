<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * The model that this factory corresponds to.
     *
     * @var class-string<Module>
     */
    protected $model = Module::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->optional()->sentence(),
            'icon' => fake()->optional()->word(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
