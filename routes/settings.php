<?php

use App\ExerciseProfiles\Http\Controllers\ArchiveExerciseProfileController;
use App\ExerciseProfiles\Http\Controllers\DeleteExerciseProfileController;
use App\ExerciseProfiles\Http\Controllers\RestoreExerciseProfileController;
use App\ExerciseProfiles\Http\Controllers\SetDefaultExerciseProfileController;
use App\ExerciseProfiles\Http\Controllers\StoreExerciseProfileController;
use App\ExerciseProfiles\Http\Controllers\SyncExerciseProfileController;
use App\ExerciseProfiles\Http\Controllers\UpdateExerciseProfileController;
use App\Settings\Http\Controllers\ExportUserDataController;
use App\Settings\Http\Controllers\PasswordController;
use App\Settings\Http\Controllers\ProfileController;
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

    Route::get('settings/training', [TrainingDefaultsController::class, 'edit'])->name('training.edit');
    Route::put('settings/training', [TrainingDefaultsController::class, 'update'])->name('training.update');
    Route::post('settings/training/reset', [TrainingDefaultsController::class, 'reset'])->name('training.reset');
    Route::put('settings/training/plates', UpdatePlateProfileController::class)->name('training.plates.update');

    Route::prefix('settings/exercise-profiles')->group(function (): void {
        Route::post('/', StoreExerciseProfileController::class)->name('exercise-profiles.store');
        Route::put('/{exerciseProfile}', UpdateExerciseProfileController::class)->name('exercise-profiles.update');
        Route::post('/{exerciseProfile}/default', SetDefaultExerciseProfileController::class)->name('exercise-profiles.default');
        Route::post('/{exerciseProfile}/sync', SyncExerciseProfileController::class)->name('exercise-profiles.sync');
        Route::post('/{exerciseProfile}/archive', ArchiveExerciseProfileController::class)->name('exercise-profiles.archive');
        Route::post('/{exerciseProfile}/restore', RestoreExerciseProfileController::class)->name('exercise-profiles.restore');
        Route::delete('/{exerciseProfile}', DeleteExerciseProfileController::class)->name('exercise-profiles.delete');
    });
});
