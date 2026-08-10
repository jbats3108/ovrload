<?php

namespace App\Workouts\Http\Controllers;

use App\Routines\Models\Routine;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\DomainFail;
use App\Workouts\Data\History\StoreHistoricalWorkoutData;
use App\Workouts\Data\Progression\ProgressionSessionData;
use App\Workouts\Data\Progression\UndoBumpProposalData;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Services\WorkoutProgressionService;
use App\Workouts\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;
use Spatie\LaravelData\DataCollection;

class StoreHistoricalWorkoutController extends Controller
{
    public function __invoke(
        StoreHistoricalWorkoutData $data,
        Routine $routine,
        WorkoutService $workoutService,
        WorkoutProgressionService $progressionService,
    ): RedirectResponse {
        try {
            [$workout, $bumps] = $workoutService->createHistoricalWorkout($routine, $data);
        } catch (WorkoutServiceException $exception) {
            return DomainFail::back($exception, 'workout');
        }

        $progressionService->forgetSiblingProgressionSessions($workout);

        if ($bumps->count() === 0) {
            return redirect()->route('history.show', $workout);
        }

        $progressionService->storeProgressionSession($workout, new ProgressionSessionData(
            bumps: $bumps,
            undos: UndoBumpProposalData::collect([], DataCollection::class),
        ));

        return redirect()->route('workouts.progression', $workout);
    }
}
