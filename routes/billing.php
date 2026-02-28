<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CreditNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing Routes
|--------------------------------------------------------------------------
|
| Routes for the water billing system including:
| - Billings (bills)
| - Payments
| - Meters
| - Meter Readings
|
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // ========================================
    // Billing Routes
    // ========================================
    Route::prefix('billings')->name('billings.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::post('/', [BillingController::class, 'store'])->name('store');
        Route::get('/create', [BillingController::class, 'create'])->name('create');
        Route::get('/{billing}', [BillingController::class, 'show'])->name('show');
        Route::put('/{billing}', [BillingController::class, 'update'])->name('update');
        Route::delete('/{billing}', [BillingController::class, 'destroy'])->name('destroy');
        Route::get('/{billing}/edit', [BillingController::class, 'edit'])->name('edit');

        // Billing actions
        Route::post('/{billing}/void', [BillingController::class, 'void'])->name('void');
        Route::post('/{billing}/rebill', [BillingController::class, 'rebill'])->name('rebill');
        Route::post('/generate-all', [BillingController::class, 'generateAll'])->name('generate-all');

        // Statements
        Route::get('/{billing}/statement', [BillingController::class, 'downloadStatement'])->name('statement.download');
        Route::post('/{billing}/statement/send', [BillingController::class, 'sendStatement'])->name('statement.send');

        // Export
        Route::get('/export/csv', [BillingController::class, 'export'])->name('export');

        // Apply credit note to a specific bill (POST from billing show page)
        Route::post('/{billing}/credit-notes', [CreditNoteController::class, 'store'])
            ->name('credit-notes.store');
    });

    // ========================================
    // Payment Routes
    // ========================================
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::get('/create', [PaymentController::class, 'create'])->name('create');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::put('/{payment}', [PaymentController::class, 'update'])->name('update');
        Route::delete('/{payment}', [PaymentController::class, 'destroy'])->name('destroy');
        Route::get('/{payment}/edit', [PaymentController::class, 'edit'])->name('edit');

        // Payment actions
        Route::post('/{payment}/reconcile', [PaymentController::class, 'reconcile'])->name('reconcile');
        Route::post('/{payment}/reverse', [PaymentController::class, 'reverseReconciliation'])->name('reverse');
        Route::post('/bulk-reconcile', [PaymentController::class, 'bulkReconcile'])->name('bulk-reconcile');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'downloadReceipt'])
            ->name('payments.receipt.download');
        Route::post('/payments/{payment}/receipt/send', [PaymentController::class, 'sendReceipt'])
            ->name('payments.receipt.send');

        // Export
        Route::get('/export/csv', [PaymentController::class, 'export'])->name('export');
    });

    // ========================================
    // Meter Routes
    // ========================================
    Route::prefix('meters')->name('meters.')->group(function () {
        Route::get('/', [MeterController::class, 'index'])->name('index');
        Route::post('/', [MeterController::class, 'store'])->name('store');
        Route::get('/create', [MeterController::class, 'create'])->name('create');
        Route::get('/{meter}', [MeterController::class, 'show'])->name('show');
        Route::put('/{meter}', [MeterController::class, 'update'])->name('update');
        Route::delete('/{meter}', [MeterController::class, 'destroy'])->name('destroy');
        Route::get('/{meter}/edit', [MeterController::class, 'edit'])->name('edit');
        Route::get('/reading-list/download', [MeterController::class, 'downloadReadingList'])
            ->name('reading-list.download');

        // Route::get('/reading-list/download', [MeterController::class, 'readingList'])
        // ->name('meters.reading-list.download');

        // Bulk meter actions
        Route::post('/{meter}/adjust-allocations', [MeterController::class, 'adjustAllocations'])->name('adjust-allocations');
        Route::post('/{meter}/validate-bulk', [MeterController::class, 'validateBulkSetup'])->name('validate-bulk');

        // Export
        Route::get('/export/csv', [MeterController::class, 'export'])->name('export');
    });

    // ========================================
    // Meter Reading Routes
    // ========================================
    Route::prefix('meter-readings')->name('meter-readings.')->group(function () {
        Route::get('/', [MeterReadingController::class, 'index'])->name('index');
        Route::post('/', [MeterReadingController::class, 'store'])->name('store');
        Route::get('/create', [MeterReadingController::class, 'create'])->name('create');
        Route::get('/{meterReading}', [MeterReadingController::class, 'show'])->name('show');
        Route::put('/{meterReading}', [MeterReadingController::class, 'update'])->name('update');
        Route::delete('/{meterReading}', [MeterReadingController::class, 'destroy'])->name('destroy');
        Route::get('/{meterReading}/edit', [MeterReadingController::class, 'edit'])->name('edit');

        // Meter-specific readings
        Route::get('/meter/{meter}', [MeterReadingController::class, 'forMeter'])->name('for-meter');

        // Bulk meter actions
        Route::post('/{meterReading}/distribute', [MeterReadingController::class, 'distribute'])->name('distribute');

        // Export
        Route::get('/export', [MeterReadingController::class, 'export'])
            ->name('export');
    });

    // ========================================
    // Credit Notes Routes
    // ========================================
    // Credit notes list

    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::get('/', [CreditNoteController::class, 'index'])->name('index');

        // Void a credit note
        Route::post('/{creditNote}/void', [CreditNoteController::class, 'void'])
            ->name('void');
    });
});
