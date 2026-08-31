<?php

namespace App\ExerciseProfiles\Http\Controllers;

use App\ExerciseProfiles\Exceptions\ExerciseProfileInUseException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteExerciseProfileController extends Controller
{
    public function __invoke(
        Request $request,
        ExerciseProfile $exerciseProfile,
        ExerciseProfileService $profiles,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('delete', $exerciseProfile);

        try {
            $profiles->delete($user, $exerciseProfile);
        } catch (ExerciseProfileInUseException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        return redirect()
            ->route('training.edit')
            ->with('success', 'Exercise profile deleted.');
    }
}
