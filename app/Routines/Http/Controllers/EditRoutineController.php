<?php

namespace App\Routines\Http\Controllers;

use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
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
    public function __invoke(Request $request, Routine $routine): Response
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
            'weight_unit' => $payload['weight_unit'],
            'warm_up_defaults' => $user->resolvedWarmUpStepsDefault(),
            'warm_up_defaults_scope' => ($user->warm_up_defaults_scope ?? WarmUpDefaultsScope::AllBlocks)->value,
            'achievement_floor_default' => $user->achievement_floor_default,
            'muscle_groups' => $muscleGroups,
            'equipment_options' => $equipmentOptions,
        ]);
    }
}
