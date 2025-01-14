<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login',[AuthController::class,'loginPage'])->name('loginPage');
Route::post('/login',[AuthController::class,'login'])->name('login');
Route::post('/logout',[AuthController::class,'logout'])->name('logout');

Route::middleware('check')->group(function () {
    Route::resource('category',App\Http\Controllers\CategoryController::class);
    Route::resource('meal',App\Http\Controllers\MealController::class);
    Route::post('/giveToCurrier', [MealController::class, 'giveToCurrier'])->name('meal.giveToCurrier');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
});
