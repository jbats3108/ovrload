<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\DomainFail;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SkipRoundController extends Controller
{
    public function __invoke(
        Request $request,
        Workout $workout,
        WorkoutBlock $block,
        WorkoutService $workoutService,
    ): RedirectResponse {
        $block->assertBelongsToWorkout($workout);

        $validated = $request->validate([
            'round_index' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $workoutService->skipRound($block, (int) $validated['round_index']);
        } catch (WorkoutServiceException $exception) {
            return DomainFail::back($exception, 'workout');
        }

        return back();
    }
}
