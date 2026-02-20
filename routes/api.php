<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\PaymentController;

// Public health check endpoint only
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| RESTful API routes for the water billing system.
| All routes require authentication via API token or Sanctum.
|
*/

// All other API endpoints require authentication and have rate limiting
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    
    // ========================================
    // Billing API Routes
    // ========================================
    Route::prefix('billings')->name('api.billings.')->group(function () {
        // List and create
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::post('/', [BillingController::class, 'store'])->name('store');
        
        // Single resource
        Route::get('/{billing}', [BillingController::class, 'show'])->name('show');
        
        // Actions
        Route::post('/{billing}/void', [BillingController::class, 'void'])->name('void');
        
        // Statistics and reports
        Route::get('/statistics/period', [BillingController::class, 'periodStatistics'])->name('statistics.period');
        Route::get('/overdue/list', [BillingController::class, 'overdue'])->name('overdue');
        
        // Account-specific
        Route::get('/account/{account}/summary', [BillingController::class, 'accountSummary'])->name('account.summary');
    });

    // ========================================
    // Payment API Routes
    // ========================================
    Route::prefix('payments')->name('api.payments.')->group(function () {
        // List and create
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        
        // Single resource
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        
        // Actions
        Route::post('/{payment}/reconcile', [PaymentController::class, 'reconcile'])->name('reconcile');
        Route::post('/{payment}/reverse', [PaymentController::class, 'reverseReconciliation'])->name('reverse');
        Route::post('/bulk-reconcile', [PaymentController::class, 'bulkReconcile'])->name('bulk-reconcile');
        
        // Statistics and reports
        Route::get('/statistics/overview', [PaymentController::class, 'statistics'])->name('statistics');
        Route::get('/unreconciled/list', [PaymentController::class, 'unreconciled'])->name('unreconciled');
        
        // Account-specific
        Route::get('/account/{account}/history', [PaymentController::class, 'accountHistory'])->name('account.history');
    });

    Route::get('/meters/reading-list/download', [MeterController::class, 'readingList'])
        ->name('meters.reading-list.download');
});

