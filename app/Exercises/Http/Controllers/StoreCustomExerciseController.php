<?php

namespace App\Exercises\Http\Controllers;

use App\Exercises\Data\CustomExerciseCreatedData;
use App\Exercises\Data\StoreCustomExerciseData;
use App\Exercises\Models\Exercise;
use App\Exercises\Services\CustomExerciseSlugGenerator;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCustomExerciseController extends Controller
{
    public function __invoke(Request $request, StoreCustomExerciseData $data): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $exercise = new Exercise([
            'user_id' => $user->id,
            'name' => $data->name,
            'slug' => CustomExerciseSlugGenerator::forUser($user, $data->name),
            'equipment' => $data->equipment,
        ]);
        $exercise->primaryMuscleGroup()->associate($data->primaryMuscleGroup);
        $exercise->secondaryMuscleGroup()->associate($data->secondaryMuscleGroup);
        $exercise->save();

        return response()->json(
            CustomExerciseCreatedData::fromExercise($exercise)->toArray(),
            201,
        );
    }
}
