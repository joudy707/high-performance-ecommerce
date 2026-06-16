<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        // Bounded writes: protects database rows from large bursts.
        // RateLimiter::for('cart_write', function (Request $request) {
        //     return [
        //         Limit::perMinute(60)->by('ip:' . $request->ip()),
        //         Limit::perMinute(20)->by('user:' . $request->input('user_id', $request->ip())),
        //     ];
        // });

        // // Searches are cheaper, but still bounded.
        // RateLimiter::for('product_search', function (Request $request) {
        //     return Limit::perMinute(600)->by($request->ip());
        // });
    }
}
