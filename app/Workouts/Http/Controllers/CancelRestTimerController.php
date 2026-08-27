<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Workouts\Models\RestTimer;
use App\Workouts\Models\Workout;
use App\Workouts\Services\RestTimerService;
use Illuminate\Http\Response;

class CancelRestTimerController extends Controller
{
    public function __invoke(
        Workout $workout,
        RestTimer $timer,
        RestTimerService $restTimerService,
    ): Response {
        $timer->assertBelongsToWorkout($workout);
        $restTimerService->cancel($timer);

        return response()->noContent();
    }
}
