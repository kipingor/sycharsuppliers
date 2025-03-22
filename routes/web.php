<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportsController;
use App\Http\Requests\StoreBillingRequest;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::resource('customers', CustomerController::class);
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('billing/create', [BillingController::class, 'create'])->name('billing.create');
    Route::post('billing', [BillingController::class, 'store'])->middleware([HandlePrecognitiveRequests::class])->name('billing.store');
    Route::get('billing/{billing}', [BillingController::class, 'show'])->name('billing.show');
    Route::get('billing/{billing}/edit', [BillingController::class, 'edit'])->name('billing.edit');
    Route::put('billing/{billing}', [BillingController::class, 'update'])->name('billing.update');
    Route::delete('billing/{billing}', [BillingController::class, 'destroy'])->name('billing.delete');
    Route::resource('meters', MeterController::class);
    Route::get('/meters/{meter}/latest-reading', [MeterController::class, 'latestReading']);
    Route::resource('reports', ReportsController::class);

    Route::resource('payments', PaymentController::class);


});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
