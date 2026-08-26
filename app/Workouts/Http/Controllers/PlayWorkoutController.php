<?php

namespace App\Workouts\Http\Controllers;

use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use App\Routines\Data\Editor\RoutineEditorExerciseOptionData;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use App\Users\Services\PlateProfileService;
use App\Workouts\Data\Player\WorkoutPlayerPageData;
use App\Workouts\Models\Workout;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayWorkoutController extends Controller
{
    public function __invoke(Request $request, Workout $workout, PlateProfileService $profiles): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('workouts/Play', [
            'workout' => WorkoutPlayerPageData::fromWorkout($workout, $user),
            'plate_profile' => $profiles->profilePayloadFor($user),
            'exercises' => Inertia::defer(fn () => Exercise::query()
                ->with(['primaryMuscleGroup', 'secondaryMuscleGroup'])
                ->forUser($user)
                ->orderBy('name')
                ->get()
                ->map(fn (Exercise $exercise): RoutineEditorExerciseOptionData => new RoutineEditorExerciseOptionData(
                    id: $exercise->id,
                    name: $exercise->getName(),
                    primaryMuscleGroup: $exercise->primaryMuscleGroup->getName(),
                    isCustom: $exercise->isCustom(),
                ))
                ->values()
                ->all()),
            'muscle_groups' => MuscleGroup::query()
                ->orderBy('name')
                ->get()
                ->map(fn (MuscleGroup $group): array => [
                    'name' => $group->getName(),
                    'slug' => $group->getSlug(),
                ])
                ->values()
                ->all(),
            'equipment_options' => array_map(
                fn (ExerciseEquipment $equipment): array => [
                    'value' => $equipment->value,
                    'label' => $equipment->label(),
                ],
                ExerciseEquipment::cases(),
            ),
        ]);
    }
}
