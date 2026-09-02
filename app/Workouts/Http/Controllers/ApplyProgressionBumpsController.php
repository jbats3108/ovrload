<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Workouts\Data\Progression\ApplyBumpsData;
use App\Workouts\Data\Progression\BumpProposalData;
use App\Workouts\Data\Progression\UndoBumpProposalData;
use App\Workouts\Models\Workout;
use App\Workouts\Services\WorkoutProgressionService;
use Illuminate\Http\RedirectResponse;

class ApplyProgressionBumpsController extends Controller
{
    public function __invoke(
        Workout $workout,
        ApplyBumpsData $data,
        WorkoutProgressionService $progressionService,
    ): RedirectResponse {
        $session = $progressionService->pullProgressionSession($workout);

        if ($session === null) {
            return redirect()->route('dashboard');
        }

        $allowedBumpIds = [];
        foreach ($session->bumps as $bump) {
            /** @var BumpProposalData $bump */
            $allowedBumpIds[] = $bump->routineBlockExerciseId;
        }
        $selectedBumps = array_intersect($data->routineBlockExerciseIds, $allowedBumpIds)
            |> array_values(...);

        $allowedUndoIds = [];
        foreach ($session->undos as $undo) {
            /** @var UndoBumpProposalData $undo */
            $allowedUndoIds[] = $undo->bumpRecordId;
        }
        $selectedUndos = array_intersect($data->undoBumpRecordIds, $allowedUndoIds)
            |> array_values(...);

        $progressionService->applyConfirmedBumps($workout, $session->bumps, $selectedBumps);
        $progressionService->applyConfirmedUndos($workout, $selectedUndos);

        return redirect()->route('dashboard');
    }
}
