<?php

namespace App\Workouts\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Workouts\Data\StartRestTimerData;
use App\Workouts\Models\Workout;
use App\Workouts\Services\RestTimerService;
use Illuminate\Http\JsonResponse;

class StartRestTimerController extends Controller
{
    public function __invoke(
        StartRestTimerData $data,
        Workout $workout,
        RestTimerService $restTimerService,
    ): JsonResponse {
        $timer = $restTimerService->start($workout, $data->seconds);

        if ($timer === null) {
            return response()->json([], 204);
        }

        return response()->json([
            'id' => $timer->id,
            'ends_at' => $timer->ends_at->toIso8601String(),
        ], 201);
    }
}
