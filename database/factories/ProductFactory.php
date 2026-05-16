<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => 'product ' . fake()->unique()->numberBetween(1, 999999) . ' phone pro new',
            'price' => fake()->randomFloat(2, 10, 500),
            'stock' => fake()->numberBetween(100, 100000),
        ];
    }
}
