<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'=> $this->faker->numberBetween(1, 51),
            'status'=> $this->faker->randomElement(['paid', 'paid', 'paid', 'paid', 'failed', 'pending']),
            'total_price'=> $this->faker->randomFloat(2, 10, 500),
            'created_at'=> today(),
                ];
    }
}
