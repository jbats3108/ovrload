<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\DomainFail;
use App\Workouts\Data\Progression\ProgressionSessionData;
use App\Workouts\Data\Progression\UndoBumpProposalData;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Services\WorkoutProgressionService;
use App\Workouts\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;
use Spatie\LaravelData\DataCollection;

class FinishWorkoutController extends Controller
{
    public function __invoke(
        Workout $workout,
        WorkoutService $workoutService,
        WorkoutProgressionService $progressionService,
    ): RedirectResponse {
        try {
            $bumps = $workoutService->finishWorkout($workout);
        } catch (WorkoutServiceException $exception) {
            return DomainFail::back($exception, 'workout');
        }

        if ($bumps->count() === 0) {
            return redirect()->route('dashboard');
        }

        $progressionService->storeProgressionSession($workout, new ProgressionSessionData(
            bumps: $bumps,
            undos: UndoBumpProposalData::collect([], DataCollection::class),
        ));

        return redirect()->route('workouts.progression', $workout);
    }
}
