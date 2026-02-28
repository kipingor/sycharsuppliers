<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountStatementController;
use App\Http\Controllers\StatementController;
use App\Http\Requests\StoreBillingRequest;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    require __DIR__ . '/billing.php';
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('accounts', AccountController::class);
    Route::post('accounts/generate-from-residents', [AccountController::class, 'generateFromResidents'])
        ->name('accounts.generate-from-residents');

    Route::prefix('accounts/{account}')->name('accounts.')->group(function () {
        Route::get('/statement', [AccountStatementController::class, 'show'])->name('statement');
        Route::get('/statement/download', [AccountStatementController::class, 'download'])->name('statement.download');
        Route::post('/statement/send', [AccountStatementController::class, 'send'])->name('statement.send');
    });

    Route::resource('residents', ResidentController::class);
    Route::resource('expenses', \App\Http\Controllers\ExpenseController::class);
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class);

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/tax', [ReportsController::class, 'taxReport'])->name('tax');
        Route::get('/tax/download', [ReportsController::class, 'downloadTaxReport'])->name('tax.download');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
