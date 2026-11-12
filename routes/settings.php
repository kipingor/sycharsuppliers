<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SystemSettingsController;
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

    Route::get('settings/system', [SystemSettingsController::class, 'edit'])->name('settings.system.edit');
    Route::put('settings/system/{group}', [SystemSettingsController::class, 'update'])->name('settings.system.update');
    Route::post('settings/system/test-email', [SystemSettingsController::class, 'testEmail'])->name('settings.system.test-email');
    Route::post('settings/system/test-mpesa', [SystemSettingsController::class, 'testMpesa'])->name('settings.system.test-mpesa');

    Route::prefix('communications')->name('communications.')->group(function () {
        Route::get('/summary', [\App\Http\Controllers\CommunicationController::class, 'summary'])->name('summary');
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\CommunicationController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/priority', [\App\Http\Controllers\CommunicationController::class, 'sendPriority'])->name('priority.store');
        Route::post('/priority/{priorityMessage}/acknowledge', [\App\Http\Controllers\CommunicationController::class, 'acknowledgePriority'])->name('priority.acknowledge');
        Route::get('/conversations', [\App\Http\Controllers\CommunicationController::class, 'conversations'])->name('conversations.index');
        Route::post('/conversations', [\App\Http\Controllers\CommunicationController::class, 'startConversation'])->name('conversations.store');
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\CommunicationController::class, 'messages'])->name('conversations.messages');
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\CommunicationController::class, 'sendChatMessage'])->name('conversations.messages.store');
        Route::post('/announcements', [\App\Http\Controllers\CommunicationController::class, 'publishAnnouncement'])->name('announcements.store');
    });
});
