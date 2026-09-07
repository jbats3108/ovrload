<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\DomainFail;
use App\Workouts\Data\CompleteWorkoutSetData;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;

class CompleteWorkoutSetController extends Controller
{
    public function __invoke(
        CompleteWorkoutSetData $data,
        Workout $workout,
        WorkoutSet $set,
        WorkoutService $workoutService,
    ): RedirectResponse {
        $set->assertBelongsToWorkout($workout);

        try {
            $workoutService->completeSet(
                $set,
                $data->reps,
                $data->weightGrams(),
                $data->segmentWeightGrams(),
                $data->plateStack?->snapshot(),
                $data->durationSeconds,
                $data->isSkipped,
            );
        } catch (WorkoutServiceException $exception) {
            return DomainFail::back($exception, 'set');
        }

        return back();
    }
}
