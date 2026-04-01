<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountStatementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ResidentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => Inertia::render('welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    require __DIR__ . '/billing.php';

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Accounts ────────────────────────────────────────────────────────────
    Route::resource('accounts', AccountController::class);
    Route::post('accounts/generate-from-residents', [AccountController::class, 'generateFromResidents'])
        ->name('accounts.generate-from-residents');
    Route::prefix('accounts/{account}')->name('accounts.')->group(function () {
        Route::get('/statement',          [AccountStatementController::class, 'show'])->name('statement');
        Route::get('/statement/download', [AccountStatementController::class, 'download'])->name('statement.download');
        Route::post('/statement/send',    [AccountStatementController::class, 'send'])->name('statement.send');
    });

    // ── Residents ────────────────────────────────────────────────────────────
    Route::resource('residents', ResidentController::class);

    // ── Expenses ────────────────────────────────────────────────────────────
    Route::resource('expenses', \App\Http\Controllers\ExpenseController::class);
    Route::post('expenses/{expense}/approve',  [\App\Http\Controllers\ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense}/reject',   [\App\Http\Controllers\ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::get('expense-budgets',              [\App\Http\Controllers\ExpenseController::class, 'budgets'])->name('expenses.budgets');
    Route::post('expense-budgets',             [\App\Http\Controllers\ExpenseController::class, 'storeBudget'])->name('expenses.budgets.store');
    Route::delete('expense-budgets/{budget}',  [\App\Http\Controllers\ExpenseController::class, 'destroyBudget'])->name('expenses.budgets.destroy');

    // ── Employees ────────────────────────────────────────────────────────────
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class);

    // ── Emails ──────────────────────────────────────────────────────────────
    Route::prefix('emails')->name('emails.')->group(function () {
        Route::get('/',                  [EmailLogController::class, 'index'])->name('index');
        Route::get('/compose',           [EmailLogController::class, 'compose'])->name('compose');
        Route::post('/send',             [EmailLogController::class, 'send'])->name('send');
        Route::get('/{emailLog}',        [EmailLogController::class, 'show'])->name('show');
        Route::get('/{emailLog}/reply',  [EmailLogController::class, 'reply'])->name('reply');
        Route::post('/{emailLog}/read',  [EmailLogController::class, 'markRead'])->name('mark-read');
        Route::delete('/{emailLog}',     [EmailLogController::class, 'destroy'])->name('destroy');
    });

    // ── Reports ──────────────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',              [ReportsController::class, 'index'])->name('index');
        Route::get('/tax',           [ReportsController::class, 'taxReport'])->name('tax');
        Route::get('/tax/download',  [ReportsController::class, 'downloadTaxReport'])->name('tax.download');
        Route::get('/expenses',      [ReportsController::class, 'expenseReport'])->name('expenses');
        Route::get('/pl',            [ReportsController::class, 'plReport'])->name('pl');
        Route::get('/aging',         [ReportsController::class, 'agingReport'])->name('aging');
        Route::get('/debtors',       [ReportsController::class, 'debtorsReport'])->name('debtors');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';