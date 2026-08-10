<?php

namespace App\Workouts\Http\Controllers;

use App\Routines\Models\Routine;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PickHistoricalWorkoutRoutineController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $routines = Routine::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Routine $routine): array => [
                'slug' => $routine->getSlug(),
                'name' => $routine->getName(),
            ])
            ->values();

        return Inertia::render('history/PickRoutine', [
            'routines' => $routines,
        ]);
    }
}
