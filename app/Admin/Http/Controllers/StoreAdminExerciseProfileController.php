<?php

namespace App\Admin\Http\Controllers;

use App\ExerciseProfiles\Data\SaveExerciseProfileData;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StoreAdminExerciseProfileController extends Controller
{
    public function __invoke(
        SaveExerciseProfileData $data,
        Request $request,
        ExerciseProfileService $profiles,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();

        try {
            $profiles->createPreset($admin, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.exercise-profiles')
            ->with('success', 'Preset draft created.');
    }
}
