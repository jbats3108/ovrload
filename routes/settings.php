<?php

use App\Settings\Http\Controllers\ExportUserDataController;
use App\Settings\Http\Controllers\NotificationSettingsController;
use App\Settings\Http\Controllers\PasswordController;
use App\Settings\Http\Controllers\ProfileController;
use App\Settings\Http\Controllers\RevokePushSubscriptionController;
use App\Settings\Http\Controllers\StorePushSubscriptionController;
use App\Settings\Http\Controllers\TrainingDefaultsController;
use App\Settings\Http\Controllers\UpdatePlateProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function (): void {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('settings/data-export', ExportUserDataController::class)->name('profile.data-export');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', fn () => Inertia::render('settings/Appearance'))->name('appearance');
    Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('notifications.edit');
    Route::post('settings/notifications/push-subscription', StorePushSubscriptionController::class)->name('notifications.subscription.store');
    Route::delete(
        'settings/notifications/push-subscription',
        RevokePushSubscriptionController::class,
    )->name('notifications.subscription.destroy');

    Route::get('settings/training', [TrainingDefaultsController::class, 'edit'])->name('training.edit');
    Route::put('settings/training', [TrainingDefaultsController::class, 'update'])->name('training.update');
    Route::post('settings/training/reset', [TrainingDefaultsController::class, 'reset'])->name('training.reset');
    Route::put('settings/training/plates', UpdatePlateProfileController::class)->name('training.plates.update');
});
