<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\RefreshController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// auth routes (public)
Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class);
Route::post('/auth/refresh', RefreshController::class);

// auth routes (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', LogoutController::class);
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);
});

// daftar route produk - yang spesifik (featured, flash-deals, price-drops) harus di atas {sku}
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/flash-deals', [ProductController::class, 'flashDeals']);
Route::get('/products/price-drops', [ProductController::class, 'priceDrops']);
Route::get('/products/{sku}', [ProductController::class, 'show']);
Route::get('/products/{sku}/related', [ProductController::class, 'related']);

// kategori & brand untuk filter sidebar
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{name}/subcategories', [CategoryController::class, 'subcategories']);
Route::get('/brands', [BrandController::class, 'index']);

// CRUD produk (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::patch('/products/{sku}', [ProductController::class, 'update']);
    Route::put('/products/{sku}', [ProductController::class, 'update']);
    Route::delete('/products/{sku}', [ProductController::class, 'destroy']);
});

// test CORS
Route::get('/test-cors', function () {
    return ['status' => 'ok'];
});
