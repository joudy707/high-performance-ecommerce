<?php

namespace Database\Factories;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Model>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
        'name' => $this->faker->words(3, true),
        'price' => $this->faker->randomFloat(2, 10, 500),
        'stock' => $this->faker->numberBetween(0, 100),
        ];
    }
}
