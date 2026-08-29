<?php

namespace App\ExerciseProfiles\Http\Controllers;

use App\ExerciseProfiles\Data\ExerciseProfileOptionData;
use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetDefaultExerciseProfileController extends Controller
{
    public function __invoke(
        Request $request,
        ExerciseProfile $exerciseProfile,
        ExerciseProfileService $profiles,
    ): RedirectResponse|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $exerciseProfile);

        try {
            $profiles->setDefault($user, $exerciseProfile);
        } catch (ExerciseProfileNotEditableException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(ExerciseProfileOptionData::fromProfile($exerciseProfile, true)->toArray());
        }

        return redirect()
            ->route('training.edit')
            ->with('success', 'Default exercise profile updated.');
    }
}
