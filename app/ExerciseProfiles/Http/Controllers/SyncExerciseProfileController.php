<?php

namespace App\ExerciseProfiles\Http\Controllers;

use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SyncExerciseProfileController extends Controller
{
    public function __invoke(
        Request $request,
        ExerciseProfile $exerciseProfile,
        ExerciseProfileService $profiles,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $profiles->syncProfile($user, $exerciseProfile);
        } catch (ExerciseProfileNotEditableException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        return redirect()
            ->route('training.edit')
            ->with('success', $updated === 1
                ? 'One exercise was updated.'
                : "{$updated} exercises were updated.");
    }
}
