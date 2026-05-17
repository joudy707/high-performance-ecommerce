<?php

namespace Database\Factories;

use App\Models\OrderItems;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItems>
 */
class OrderItemsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        
            //
            return [
    'order_id'   => $this->faker->numberBetween(1, 2000),  
    'product_id' => $this->faker->numberBetween(1, 100),  
    'quantity'   => $this->faker->numberBetween(1, 10),    
    'price'      => $this->faker->randomFloat(2, 10, 500),   
];
    }
}
