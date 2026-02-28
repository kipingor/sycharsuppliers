<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
    Route::get('settings/users', [UserController::class, 'index'])->name('settings.users.index');
    Route::post('settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::patch('settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
    Route::delete('settings/users/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');
    // Route::get('settings/users', [SettingsController::class, 'index'])->name('users');
});
