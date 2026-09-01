<?php

namespace App\Exercises\Support;

use App\Exercises\Models\Exercise;
use App\Routines\Data\Editor\RoutineEditorExerciseOptionData;
use App\Users\Models\User;
use Inertia\DeferProp;
use Inertia\Inertia;

final class ExercisePickerOptions
{
    /**
     * @return DeferProp
     */
    public static function deferFor(User $user): mixed
    {
        return Inertia::defer(fn (): array => self::listFor($user));
    }

    /**
     * @return list<RoutineEditorExerciseOptionData>
     */
    public static function listFor(User $user): array
    {
        return Exercise::query()
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
            ->all();
    }
}
