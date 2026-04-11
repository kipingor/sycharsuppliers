<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\ResendWebhookController;

// Public health check
Route::get('/health', fn() => response()->json(['status' => 'ok']));

/*
|--------------------------------------------------------------------------
| Resend Webhooks
|--------------------------------------------------------------------------
|
| This route has NO auth and NO CSRF — Resend posts from external
| servers. Svix signature verification is handled inside the controller.
|
| Register this URL in your Resend dashboard for the events you need:
|   email.sent, email.delivered, email.opened, email.clicked,
|   email.bounced, email.failed, email.complained, email.received
|
|   https://yourdomain.com/api/webhooks/resend
|
*/
Route::post('/webhooks/resend', [ResendWebhookController::class, 'handle'])
    ->name('webhooks.resend');

/*
|--------------------------------------------------------------------------
| Authenticated API (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

    Route::prefix('billings')->name('api.billings.')->group(function () {
        Route::get('/',                              [BillingController::class, 'index'])->name('index');
        Route::post('/',                             [BillingController::class, 'store'])->name('store');
        Route::get('/{billing}',                     [BillingController::class, 'show'])->name('show');
        Route::post('/{billing}/void',               [BillingController::class, 'void'])->name('void');
        Route::get('/statistics/period',             [BillingController::class, 'periodStatistics'])->name('statistics.period');
        Route::get('/overdue/list',                  [BillingController::class, 'overdue'])->name('overdue');
        Route::get('/account/{account}/summary',     [BillingController::class, 'accountSummary'])->name('account.summary');
    });

    Route::prefix('payments')->name('api.payments.')->group(function () {
        Route::get('/',                              [PaymentController::class, 'index'])->name('index');
        Route::post('/',                             [PaymentController::class, 'store'])->name('store');
        Route::get('/{payment}',                     [PaymentController::class, 'show'])->name('show');
        Route::post('/{payment}/reconcile',          [PaymentController::class, 'reconcile'])->name('reconcile');
        Route::post('/{payment}/reverse',            [PaymentController::class, 'reverseReconciliation'])->name('reverse');
        Route::post('/bulk-reconcile',               [PaymentController::class, 'bulkReconcile'])->name('bulk-reconcile');
        Route::get('/statistics/overview',           [PaymentController::class, 'statistics'])->name('statistics');
        Route::get('/unreconciled/list',             [PaymentController::class, 'unreconciled'])->name('unreconciled');
        Route::get('/account/{account}/history',     [PaymentController::class, 'accountHistory'])->name('account.history');
    });

});
