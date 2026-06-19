<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesController;
use App\Jobs\GenerateDailySalesReport;
use Illuminate\Support\Facades\Cache;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'server' => gethostname(),
        'timestamp' => now(),
    ], 200);
});


Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);


Route::get('/products', [ProductController::class, 'index'])->middleware('checkAuth:admin');
Route::get('/products/{product_id}', [ProductController::class, 'show'])->middleware('checkAuth:admin');

// Admin/product endpoints.
Route::post('/products', [ProductController::class, 'addProduct']);
Route::post('/products/{product_id}/stock', [ProductController::class, 'addToStock']);




// Task 1 - correctness/race-condition comparison on order confirmation.
Route::post('/order/{product_id}', [OrderController::class, 'createOrder']);
Route::post('/orders/{order_id}/confirm-broken', [OrderController::class, 'confirmOrderBroken']);
Route::post('/orders/{order_id}/confirm-fixed', [OrderController::class, 'confirmOrderFixed']);
Route::get('/orders/{order_id}', [OrderController::class, 'showOrder']);

#Task 8
Route::post('/orders/{order_id}/confirm-broken-acid', [OrderController::class, 'confirmOrderBrokenACID']);
Route::post('/orders/{order_id}/confirm-fixed-acid', [OrderController::class, 'confirmOrderFixedACID']);


// Task 7 - Distributed Lock 
Route::post('/orders/{order_id}/confirm-broken-distributed', [OrderController::class, 'confirmOrderBrokenDistributed']);
Route::post('/orders/{order_id}/confirm-fixed-distributed', [OrderController::class, 'confirmOrderFixedDistributed']);
Route::post('/sales/report/generate-broken-lock', function () {
    \App\Jobs\GenerateReportBrokenLock::dispatch();
    return response()->json([
        'message' => 'Report job dispatched WITHOUT distributed lock.',
        'server'  => gethostname(),
        'warning' => 'If two servers hit this simultaneously, the report runs TWICE.',
    ]);
});

Route::post('/sales/report/generate-fixed-lock', function () {
    \App\Jobs\GenerateReportFixedLock::dispatch();
    return response()->json([
        'message' => 'Report job dispatched WITH Redis distributed lock.',
        'server'  => gethostname(),
        'note'    => 'Only one server will actually generate the report. The other will be skipped.',
    ]);
});

// Task 2 - resource-management comparison: add to cart
Route::post('/cart/{product_id}/add-broken', [CartController::class, 'addToCartBroken']);
Route::middleware(['throttle:cart_write'])->group(function () {
    Route::post('/cart/{product_id}/add-fixed', [CartController::class, 'addToCartFixed']);
});
// Task 2 - resource-management comparison: search 
Route::get('/products-search-broken', [ProductController::class, 'searchBroken']);
Route::middleware(['throttle:product_search'])->group(function () {
    Route::get('/products-search-fixed', [ProductController::class, 'searchFixed']);
});


# role 3
Route::post('/orders/{order_id}/confirm-broken-async', [OrderController::class, 'confirmOrderBrokenAsync']);
Route::post('/orders/{order_id}/confirm-fixed-async',  [OrderController::class, 'confirmOrderFixedAsync']);


Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);



#Task 4
Route::get('/sales/report/confirm-broken',  [SalesController::class, 'brokenReport']);
Route::get('/sales/report/confirm-fixed',   [SalesController::class, 'fixedReport']);
Route::post('/sales/report/generate', function () {
    GenerateDailySalesReport::dispatch();
    return response()->json(['message' => 'Report generation started. Workers are processing chunks in parallel.']);
});

#Task6
#broken
Route::get('/cache/top-products/broken',[ProductController::class,'topSellingProductsBroken']);
Route::get('/cache/product/broken/{id}',[ProductController::class,'cachedProductBroken']);
Route::get('/products-search/broken', [ProductController::class, 'searchFixed']);
#fixed
Route::get('/cache/top-products',[ProductController::class,'topSellingProducts']);
Route::get('/products-search', [ProductController::class, 'searchCache']);
Route::get( '/cache/product/{id}',[ProductController::class,'cachedProduct']);

Route::get('/cache/stats',[ProductController::class,'cacheStats']);
Route::post('/cache/clear',[ProductController::class,'clearCache']);