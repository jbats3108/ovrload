<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\DomainFail;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;

class SkipRestOfBlockController extends Controller
{
    public function __invoke(
        Workout $workout,
        WorkoutBlock $block,
        WorkoutService $workoutService,
    ): RedirectResponse {
        $block->assertBelongsToWorkout($workout);

        try {
            $workoutService->skipRestOfBlock($block);
        } catch (WorkoutServiceException $exception) {
            return DomainFail::back($exception, 'workout');
        }

        return back();
    }
}
