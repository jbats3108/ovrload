<?php

use App\Admin\Http\Controllers\IndexAdminExercisesController;
use App\Admin\Http\Controllers\IndexAdminInvitesController;
use App\Admin\Http\Controllers\IndexAdminMuscleGroupsController;
use App\Admin\Http\Controllers\IndexAdminUsersController;
use App\Admin\Http\Controllers\ResendAdminInviteController;
use App\Admin\Http\Controllers\RevokeAdminInviteController;
use App\Admin\Http\Controllers\ShowAdminController;
use App\Admin\Http\Controllers\StoreAdminInviteController;
use App\Dashboard\Http\Controllers\ShowDashboardController;
use App\Exercises\Http\Controllers\DeleteExerciseController;
use App\Exercises\Http\Controllers\StoreCustomExerciseController;
use App\Exercises\Http\Controllers\StoreExerciseController;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Http\Controllers\DeleteMuscleGroupController;
use App\MuscleGroups\Http\Controllers\StoreMuscleGroupController;
use App\MuscleGroups\Models\MuscleGroup;
use App\Routines\Http\Controllers\CreateRoutineController;
use App\Routines\Http\Controllers\DeleteRoutineController;
use App\Routines\Http\Controllers\DuplicateRoutineController;
use App\Routines\Http\Controllers\EditRoutineController;
use App\Routines\Http\Controllers\ShowRoutineController;
use App\Routines\Http\Controllers\StoreRoutineController;
use App\Routines\Http\Controllers\UpdateRoutineController;
use App\Shared\Http\Controllers\ShowBetaTesterFaqsController;
use App\Shared\Http\Controllers\ShowFeedbackController;
use App\Shared\Http\Controllers\ShowHomeController;
use App\Shared\Http\Controllers\ShowInviteRequestController;
use App\Shared\Http\Controllers\ShowPrivacyController;
use App\Shared\Http\Controllers\ShowTutorialController;
use App\Shared\Http\Controllers\StoreFeedbackController;
use App\Shared\Http\Controllers\StoreInviteRequestController;
use App\Workouts\Http\Controllers\AddAdHocExerciseController;
use App\Workouts\Http\Controllers\AddWorkingSetController;
use App\Workouts\Http\Controllers\ApplyProgressionBumpsController;
use App\Workouts\Http\Controllers\CompleteWorkoutSetController;
use App\Workouts\Http\Controllers\CreateHistoricalWorkoutController;
use App\Workouts\Http\Controllers\DeleteWorkoutHistoryController;
use App\Workouts\Http\Controllers\DiscardWorkoutController;
use App\Workouts\Http\Controllers\FinishWorkoutController;
use App\Workouts\Http\Controllers\IndexWorkoutHistoryController;
use App\Workouts\Http\Controllers\PickHistoricalWorkoutRoutineController;
use App\Workouts\Http\Controllers\PlayWorkoutController;
use App\Workouts\Http\Controllers\PromoteWorkoutSetToDropsetController;
use App\Workouts\Http\Controllers\RemoveAdHocBlockController;
use App\Workouts\Http\Controllers\RemoveWorkingSetController;
use App\Workouts\Http\Controllers\ShowProgressionController;
use App\Workouts\Http\Controllers\ShowWorkoutHistoryController;
use App\Workouts\Http\Controllers\SkipProgressionController;
use App\Workouts\Http\Controllers\SkipRestOfBlockController;
use App\Workouts\Http\Controllers\StoreHistoricalWorkoutController;
use App\Workouts\Http\Controllers\StoreWorkoutController;
use App\Workouts\Http\Controllers\UpdateWorkoutHistorySetController;
use App\Workouts\Models\Workout;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowHomeController::class)->name('home');

Route::get('beta-tester-faqs', ShowBetaTesterFaqsController::class)->name('beta-tester-faqs');

Route::get('invite-request', ShowInviteRequestController::class)->name('invite-request');
Route::post('invite-request', StoreInviteRequestController::class)
    ->middleware('throttle:5,1')
    ->name('invite-request.store');

Route::get('feedback', ShowFeedbackController::class)->name('feedback');
Route::post('feedback', StoreFeedbackController::class)
    ->middleware('throttle:5,1')
    ->name('feedback.store');

Route::get('privacy', ShowPrivacyController::class)->name('privacy');

Route::get('tutorial', ShowTutorialController::class)->name('tutorial');

Route::middleware('auth')->group(function (): void {

    Route::get('dashboard', ShowDashboardController::class)->name('dashboard');

    Route::prefix('exercises')->group(function (): void {
        Route::post('/create', StoreExerciseController::class)
            ->can('create', Exercise::class)
            ->name('exercises.store');

        Route::post('/custom', StoreCustomExerciseController::class)
            ->middleware('throttle:30,1')
            ->can('createCustom', Exercise::class)
            ->name('exercises.custom.store');

        Route::delete('/{exercise}', DeleteExerciseController::class)
            ->can('delete', 'exercise')
            ->name('exercises.delete');
    });

    Route::prefix('routines')->group(function (): void {
        Route::get('/create', CreateRoutineController::class)
            ->name('routines.create');

        Route::post('/create', StoreRoutineController::class)
            ->name('routines.store');

        Route::get('/{routine}/edit', EditRoutineController::class)
            ->can('view', 'routine')
            ->name('routines.edit');

        Route::get('/{routine}', ShowRoutineController::class)
            ->can('view', 'routine')
            ->name('routines.show');

        Route::delete('/{routine}', DeleteRoutineController::class)
            ->can('delete', 'routine')
            ->name('routines.delete');

        Route::post('/{routine}/duplicate', DuplicateRoutineController::class)
            ->can('duplicate', 'routine')
            ->name('routines.duplicate');

        Route::put('/{routine}', UpdateRoutineController::class)
            ->can('update', 'routine')
            ->name('routines.update');
    });

    Route::prefix('/muscle-groups')->group(function (): void {
        Route::post('/create', StoreMuscleGroupController::class)
            ->can('create', MuscleGroup::class)
            ->name('muscle-groups.store');

        Route::delete('/{muscleGroup}', DeleteMuscleGroupController::class)
            ->can('delete', MuscleGroup::class)
            ->name('muscle-groups.delete');
    });

    Route::prefix('/history')->group(function (): void {
        Route::get('/', IndexWorkoutHistoryController::class)->name('history.index');

        Route::get('/create', PickHistoricalWorkoutRoutineController::class)
            ->name('history.create.pick');

        Route::get('/create/{routine}', CreateHistoricalWorkoutController::class)
            ->can('create', [Workout::class, 'routine'])
            ->name('history.create');

        Route::post('/create/{routine}', StoreHistoricalWorkoutController::class)
            ->can('create', [Workout::class, 'routine'])
            ->name('history.store');

        Route::get('/{workout}', ShowWorkoutHistoryController::class)
            ->can('view', 'workout')
            ->name('history.show');

        Route::put('/{workout}/sets/{set}', UpdateWorkoutHistorySetController::class)
            ->can('editHistory', 'workout')
            ->name('history.sets.update');

        Route::delete('/{workout}', DeleteWorkoutHistoryController::class)
            ->can('deleteHistory', 'workout')
            ->name('history.destroy');
    });

    Route::prefix('/workouts')->group(function (): void {
        Route::post('/create/{routine}', StoreWorkoutController::class)
            ->can('create', [Workout::class, 'routine'])
            ->name('workouts.store');

        Route::get('/{workout}/play', PlayWorkoutController::class)
            ->can('view', 'workout')
            ->name('workouts.play');

        Route::post('/{workout}/sets/{set}', CompleteWorkoutSetController::class)
            ->can('update', 'workout')
            ->name('workouts.sets.complete');

        Route::post('/{workout}/sets/{set}/promote-dropset', PromoteWorkoutSetToDropsetController::class)
            ->can('update', 'workout')
            ->name('workouts.sets.promote-dropset');

        Route::post('/{workout}/blocks/{block}/working-sets', AddWorkingSetController::class)
            ->can('update', 'workout')
            ->name('workouts.working-sets.add');

        Route::post('/{workout}/ad-hoc-exercises', AddAdHocExerciseController::class)
            ->can('update', 'workout')
            ->name('workouts.ad-hoc-exercises.store');

        Route::delete('/{workout}/blocks/{block}/ad-hoc', RemoveAdHocBlockController::class)
            ->can('update', 'workout')
            ->name('workouts.ad-hoc-blocks.destroy');

        Route::post('/{workout}/blocks/{block}/skip-rest', SkipRestOfBlockController::class)
            ->can('update', 'workout')
            ->name('workouts.blocks.skip-rest');

        Route::delete('/{workout}/sets/{set}', RemoveWorkingSetController::class)
            ->can('update', 'workout')
            ->name('workouts.sets.remove');

        Route::post('/{workout}/finish', FinishWorkoutController::class)
            ->can('update', 'workout')
            ->name('workouts.finish');

        Route::post('/{workout}/discard', DiscardWorkoutController::class)
            ->can('update', 'workout')
            ->name('workouts.discard');

        Route::get('/{workout}/progression', ShowProgressionController::class)
            ->can('view', 'workout')
            ->name('workouts.progression');

        Route::post('/{workout}/progression', ApplyProgressionBumpsController::class)
            ->can('applyProgression', 'workout')
            ->name('workouts.progression.apply');

        Route::post('/{workout}/progression/skip', SkipProgressionController::class)
            ->can('dismissProgression', 'workout')
            ->name('workouts.progression.skip');
    });

    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::get('/', ShowAdminController::class)->name('admin.index');
        Route::get('/exercises', IndexAdminExercisesController::class)->name('admin.exercises');
        Route::get('/muscle-groups', IndexAdminMuscleGroupsController::class)->name('admin.muscle-groups');
        Route::get('/users', IndexAdminUsersController::class)->name('admin.users');
        Route::get('/invites', IndexAdminInvitesController::class)->name('admin.invites');
        Route::post('/invites', StoreAdminInviteController::class)->name('admin.invites.store');
        Route::post('/invites/{invite}/resend', ResendAdminInviteController::class)->name('admin.invites.resend');
        Route::post('/invites/{invite}/revoke', RevokeAdminInviteController::class)->name('admin.invites.revoke');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
