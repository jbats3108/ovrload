<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\DomainFail;
use App\Workouts\Data\History\UpdateWorkoutHistoryData;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Services\WorkoutHistoryService;
use Illuminate\Http\RedirectResponse;

class UpdateWorkoutHistoryController extends Controller
{
    public function __invoke(
        UpdateWorkoutHistoryData $data,
        Workout $workout,
        WorkoutHistoryService $historyService,
    ): RedirectResponse {
        try {
            $session = $historyService->updateWorkingSets($workout, $data);
        } catch (WorkoutServiceException $exception) {
            return DomainFail::back($exception, 'sets');
        }

        if ($session !== null) {
            return redirect()
                ->route('workouts.progression', $workout)
                ->with('success', 'Workout saved.');
        }

        return back()->with('success', 'Workout saved.');
    }
}
