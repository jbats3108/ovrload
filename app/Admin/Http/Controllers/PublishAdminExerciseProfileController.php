<?php

namespace App\Admin\Http\Controllers;

use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PublishAdminExerciseProfileController extends Controller
{
    public function __invoke(
        Request $request,
        ExerciseProfile $exerciseProfile,
        ExerciseProfileService $profiles,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();
        Gate::authorize('publish', $exerciseProfile);

        try {
            $profiles->publishPreset($admin, $exerciseProfile);
        } catch (ExerciseProfileNotEditableException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.exercise-profiles')
            ->with('success', 'Preset published as an OVRLOAD preset.');
    }
}
