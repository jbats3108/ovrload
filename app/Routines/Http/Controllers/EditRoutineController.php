<?php

namespace App\Routines\Http\Controllers;

use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Support\ExercisePickerOptions;
use App\MuscleGroups\Models\MuscleGroup;
use App\Routines\Data\Editor\RoutineEditorExerciseOptionData;
use App\Routines\Data\Editor\RoutineEditorPageData;
use App\Routines\Models\Routine;
use App\Shared\Http\Controllers\Controller;
use App\Users\Enums\WarmUpDefaultsScope;
use App\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

class EditRoutineController extends Controller
{
    public function __invoke(Request $request, Routine $routine, ExerciseProfileService $exerciseProfiles): Response
    {
        /** @var User $user */
        $user = $request->user();

        $page = RoutineEditorPageData::fromRoutine(
            $routine,
            RoutineEditorExerciseOptionData::collect([], DataCollection::class),
            $user->weight_unit->value,
        );

        $payload = $page->toArray();

        $muscleGroups = MuscleGroup::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MuscleGroup $group): array => [
                'name' => $group->getName(),
                'slug' => $group->getSlug(),
            ])
            ->values()
            ->all();

        $equipmentOptions = array_map(
            fn (ExerciseEquipment $equipment): array => [
                'value' => $equipment->value,
                'label' => $equipment->label(),
            ],
            ExerciseEquipment::cases(),
        );

        return Inertia::render('routines/Edit', [
            'routine' => Arr::except($payload, ['exercises', 'weight_unit']),
            'exercises' => ExercisePickerOptions::deferFor($user),
            'weight_unit' => $payload['weight_unit'],
            'warm_up_defaults' => $user->resolvedWarmUpStepsDefault(),
            'warm_up_defaults_scope' => ($user->warm_up_defaults_scope ?? WarmUpDefaultsScope::AllBlocks)->value,
            'achievement_floor_default' => $user->achievement_floor_default,
            'progression_target_default' => $user->resolvedDefaultTargetReps(),
            'muscle_groups' => $muscleGroups,
            'equipment_options' => $equipmentOptions,
            'exercise_profiles' => $exerciseProfiles->optionsForRoutineEditor($user, $routine),
        ]);
    }
}
