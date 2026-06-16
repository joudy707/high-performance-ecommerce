<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function updated(Product $product): void
    {
        Cache::forget("product_details_{$product->id}");
        Cache::forget('top_selling_products');

        //  إبطال كاش كل عمليات البحث لأن مواصفات منتج قد تغيرت
        try {
            Cache::tags(['search_queries'])->flush();
        } catch (\Throwable $e) {}
    }

    public function deleted(Product $product): void
    {
        Cache::forget("product_details_{$product->id}");
        Cache::forget('top_selling_products');

        //  إبطال كاش البحث عند حذف منتج لكي لا يظهر في النتائج
        try {
            Cache::tags(['search_queries'])->flush();
        } catch (\Throwable $e) {}
    }
    
    public function created(Product $product): void
    {
        Cache::forget('top_selling_products');

        //  إبطال كاش البحث عند إضافة منتج جديد لكي يظهر فوراً للزبائن
        try {
            Cache::tags(['search_queries'])->flush();
        } catch (\Throwable $e) {}
    }
}