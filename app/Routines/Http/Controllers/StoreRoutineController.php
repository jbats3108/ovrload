<?php

namespace App\Routines\Http\Controllers;

use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Routines\Data\StoreRoutineData;
use App\Routines\Models\Routine;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class StoreRoutineController extends Controller
{
    public function __invoke(StoreRoutineData $request, ExerciseProfileService $profiles): RedirectResponse
    {
        $profile = ExerciseProfile::query()->findOrFail($request->defaultExerciseProfileId);
        try {
            $profiles->assertSelectable($request->user, $profile);
        } catch (ExerciseProfileNotEditableException $exception) {
            throw ValidationException::withMessages(['default_exercise_profile_id' => $exception->getMessage()]);
        }

        $routine = Routine::create([
            'user_id' => $request->user->id,
            'name' => $request->name,
            'default_exercise_profile_id' => $profile->id,
            'deload_weight_factor' => $request->deloadWeightFactor ?? (float) $request->user->deload_weight_factor_default,
            'deload_reps_factor' => $request->deloadRepsFactor ?? (float) $request->user->deload_reps_factor_default,
            'deload_every_n' => $request->deloadEveryN ?? (int) $request->user->deload_every_n_default,
        ]);

        return redirect(route('routines.edit', $routine))->with('success', 'Routine has been created.');
    }
}
