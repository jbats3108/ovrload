<?php

namespace App\Admin\Http\Controllers;

use App\ExerciseProfiles\Data\SaveExerciseProfileData;
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

class UpdateAdminExerciseProfileController extends Controller
{
    public function __invoke(
        SaveExerciseProfileData $data,
        Request $request,
        ExerciseProfile $exerciseProfile,
        ExerciseProfileService $profiles,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();
        Gate::authorize('update', $exerciseProfile);

        try {
            $profiles->updatePresetDraft($admin, $exerciseProfile, $data);
        } catch (ExerciseProfileNotEditableException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.exercise-profiles')
            ->with('success', 'Preset draft saved.');
    }
}
