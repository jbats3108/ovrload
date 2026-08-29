<?php

namespace App\ExerciseProfiles\Http\Controllers;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RestoreExerciseProfileController extends Controller
{
    public function __invoke(
        Request $request,
        ExerciseProfile $exerciseProfile,
        ExerciseProfileService $profiles,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('restore', $exerciseProfile);

        try {
            $profiles->restore($user, $exerciseProfile);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        return redirect()
            ->route('training.edit')
            ->with('success', 'Exercise profile restored.');
    }
}
