<?php

namespace App\ExerciseProfiles\Http\Controllers;

use App\ExerciseProfiles\Data\SaveExerciseProfileData;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UpdateExerciseProfileController extends Controller
{
    public function __invoke(
        SaveExerciseProfileData $data,
        Request $request,
        ExerciseProfile $exerciseProfile,
        ExerciseProfileService $profiles,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('update', $exerciseProfile);

        try {
            $exerciseProfile = $profiles->updateCustom($user, $exerciseProfile, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return redirect()
            ->route('training.edit')
            ->with('success', 'Exercise profile saved.')
            ->with('profile_sync_id', $exerciseProfile->id);
    }
}
