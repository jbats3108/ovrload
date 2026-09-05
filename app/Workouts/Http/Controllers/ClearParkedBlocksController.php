<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\DomainFail;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;

class ClearParkedBlocksController extends Controller
{
    public function __invoke(
        Workout $workout,
        WorkoutService $workoutService,
    ): RedirectResponse {
        try {
            $workoutService->clearParkedBlocks($workout);
        } catch (WorkoutServiceException $exception) {
            return DomainFail::back($exception, 'workout');
        }

        return back();
    }
}
