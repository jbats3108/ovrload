<?php

namespace App\Routines\Http\Controllers;

use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreateRoutineController extends Controller
{
    public function __invoke(Request $request, ExerciseProfileService $exerciseProfiles): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('routines/Create', [
            'exercise_profiles' => $exerciseProfiles->optionsForUser($user, $user->default_exercise_profile_id),
            'default_exercise_profile_id' => $user->default_exercise_profile_id,
        ]);
    }
}
