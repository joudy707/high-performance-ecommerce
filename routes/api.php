<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::get('/products',[ProductController::class,'index'])->middleware('checkAuth:admin');
Route::get('/products/{product_id}',[ProductController::class,'show'])->middleware('checkAuth:admin');

#shoul add admin middleware
Route::post('/products', [ProductController::class, 'addProduct']);
Route::post('/products/{product_id}/stock', [ProductController::class, 'addToStock']);



#Task 1
Route::post('/order/{product_id}', [OrderController::class, 'createOrder']);
Route::post('/orders/{order_id}/confirm-broken', [OrderController::class, 'confirmOrderBroken']);
Route::post('/orders/{order_id}/confirm-fixed', [OrderController::class, 'confirmOrderFixed']);
Route::get('/orders/{order_id}', [OrderController::class, 'showOrder']);


#Task 1
Route::post('/order/{product_id}', [OrderController::class, 'createOrder']);
Route::post('/orders/{order_id}/confirm-broken', [OrderController::class, 'confirmOrderBroken']);
Route::post('/orders/{order_id}/confirm-fixed', [OrderController::class, 'confirmOrderFixed']);
Route::get('/orders/{order_id}', [OrderController::class, 'showOrder']);


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


