<?php

namespace App\ExerciseProfiles\Http\Controllers;

use App\ExerciseProfiles\Data\ExerciseProfileOptionData;
use App\ExerciseProfiles\Data\SaveExerciseProfileData;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StoreExerciseProfileController extends Controller
{
    public function __invoke(
        SaveExerciseProfileData $data,
        Request $request,
        ExerciseProfileService $profiles,
    ): RedirectResponse|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $profile = $profiles->createCustom($user, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(ExerciseProfileOptionData::fromProfile($profile)->toArray(), 201);
        }

        return redirect()
            ->route('training.edit')
            ->with('success', 'Custom exercise profile created.');
    }
}
