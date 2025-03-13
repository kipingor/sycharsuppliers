<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
    Route::resource('customers', CustomerController::class);
    Route::resource('billing', BillingController::class);
    Route::resource('meters', MeterController::class);
    Route::resource('payments', PaymentController::class);


});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
