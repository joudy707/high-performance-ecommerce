<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesController;
use App\Jobs\GenerateDailySalesReport;



Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::get('/products',[ProductController::class,'index']);


Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);



#Task 4
Route::get('/sales/report/confirm-broken',  [SalesController::class, 'brokenReport']);
Route::get('/sales/report/confirm-fixed',   [SalesController::class, 'fixedReport']);
Route::post('/sales/report/generate', function () {
    GenerateDailySalesReport::dispatch();
    return response()->json(['message' => 'Report generation started. Workers are processing chunks in parallel.']);
});

