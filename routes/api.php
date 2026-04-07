<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserInteractionController;

// ── Public routes ──────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Public product browsing (for the public-facing site)
Route::get('/public/products',    [ProductController::class, 'publicIndex']);
Route::get('/public/products/{id}', [ProductController::class, 'publicShow']);
Route::post('/public/products/{product}/view', [ProductController::class, 'incrementView'])
    ->middleware('throttle:public-views');
Route::get('/public/categories',  [CategoryController::class, 'index']);
Route::get('/public/hero-images', [\App\Http\Controllers\HeroImageController::class, 'index']);
Route::get('/public/settings',    [\App\Http\Controllers\SettingController::class, 'index']);
Route::post('/public/interactions', [UserInteractionController::class, 'store'])
    ->middleware('throttle:public-interactions');

// Public salespersons (for enquiry dropdown)
Route::get('/public/salespersons', [\App\Http\Controllers\Api\SalespersonController::class, 'publicIndex']);

// ── Protected Admin routes ──────────────────────────────────
Route::middleware(['auth:sanctum', 'active.device', 'throttle:admin-api'])->group(function () {
    Route::get('/me',              [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout',         [AuthController::class, 'logout']);

    // Categories
    Route::get('/categories',      [CategoryController::class, 'index']);
    Route::post('/categories',     [CategoryController::class, 'store']);
    Route::put('/categories/{category}',  [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // Products
    Route::get('/products',        [ProductController::class, 'index']);
    Route::post('/products',       [ProductController::class, 'store']);
    Route::match(['post', 'put'], '/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    // Settings
    Route::post('/settings',       [\App\Http\Controllers\SettingController::class, 'update']);

    // Hero Images
    Route::get('/admin/hero-images',     [\App\Http\Controllers\HeroImageController::class, 'adminIndex']);
    Route::post('/hero-images',          [\App\Http\Controllers\HeroImageController::class, 'store']);
    Route::put('/hero-images/{heroImage}', [\App\Http\Controllers\HeroImageController::class, 'update']);
    Route::delete('/hero-images/{heroImage}', [\App\Http\Controllers\HeroImageController::class, 'destroy']);

    // Interactions
    Route::get('/interactions', [UserInteractionController::class, 'index']);
    Route::patch('/interactions/{interaction}/read', [UserInteractionController::class, 'markAsRead']);
    Route::delete('/interactions/{interaction}', [UserInteractionController::class, 'destroy']);
    Route::post('/interactions/clear', [UserInteractionController::class, 'clearAll']);

    // Salespersons
    Route::get('/salespersons',                  [\App\Http\Controllers\Api\SalespersonController::class, 'index']);
    Route::post('/salespersons',                 [\App\Http\Controllers\Api\SalespersonController::class, 'store']);
    Route::put('/salespersons/{salesperson}',    [\App\Http\Controllers\Api\SalespersonController::class, 'update']);
    Route::delete('/salespersons/{salesperson}', [\App\Http\Controllers\Api\SalespersonController::class, 'destroy']);
});
