<?php

namespace App\Services;

use App\Models\Product;

class StockService
{
    public function decreaseStock(Product $product, int $quantity)
    {
        if ($product->stock < $quantity) {
            throw new \Exception('Out of stock');
        }

        // NAIVE (قبل التحسين) 
        $product->stock -= $quantity;
        $product->save();

      
    }
}
