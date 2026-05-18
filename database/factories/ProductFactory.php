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

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
  public function definition(): array
{
    $price = $this->faker->randomFloat(2, 10, 500);

    return [
        'name'  => $this->faker->words(3, true),
        'price'=> $price,
        'stock' => $this->faker->numberBetween(0, 100),
        'cost_price' => round($price * $this->faker->randomFloat(2, 0.5, 0.75), 2),
    ];
}

}
