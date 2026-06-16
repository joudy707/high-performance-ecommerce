<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class ProductDailySalesSeeder extends Seeder
{
    public function run(): void
    {
        // جلب المنتجات الموجودة التي تم توليدها من ProductSeeder
        $products = Product::all();

        if ($products->isEmpty()) {
            return;
        }

       foreach ($products as $product) {
    // توليد كمية مبيعات عشوائية لكل منتج
    $quantitySold = rand(10, 150);
    $revenue = $quantitySold * $product->price;
    $netProfit = $revenue * 0.4; // افتراض أن صافي الربح هو 40%

    DB::table('product_daily_sales')->insert([
        'product_id'        => $product->id,
        'quantity_sold'     => $quantitySold,
        'revenue_generated' => $revenue,
        'net_profit'        => $netProfit,
        'date'              => now()->format('Y-m-d'), // 🌟 إدخال تاريخ اليوم لحل المشكلة
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
}
    }
}