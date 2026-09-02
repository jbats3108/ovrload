<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineSetGroup;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Models\WorkoutSet;

trait SeedsPlayableRoutineBlock
{
    /**
     * @return array{0: RoutineSetGroup, 1: RoutineBlockExercise}
     */
    protected function seedPlayableRoutineBlock(
        Routine $routine,
        int $setCount = 1,
        ?int $restSeconds = 90,
        int $workingWeightG = 80000,
        int $prescribedReps = 6,
        ?int $progressionTarget = null,
        ?int $achievementFloor = null,
    ): array {
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
        ]);

        $exerciseAttributes = [
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => $workingWeightG,
            'prescribed_reps' => $prescribedReps,
        ];

        if ($progressionTarget !== null) {
            $exerciseAttributes['progression_target_override'] = $progressionTarget;
        }

        if ($achievementFloor !== null) {
            $exerciseAttributes['achievement_floor_override'] = $achievementFloor;
        }

        $routineExercise = RoutineBlockExercise::create($exerciseAttributes);

        $groupAttributes = [
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => $setCount,
        ];

        if ($restSeconds !== null) {
            $groupAttributes['rest_seconds'] = $restSeconds;
        }

        $group = RoutineSetGroup::create($groupAttributes);

        return [$group, $routineExercise];
    }

    protected function firstWorkingSet(int $workoutId): WorkoutSet
    {
        return WorkoutSet::query()
            ->whereHas('setGroup', fn ($q) => $q->where('type', SetGroupType::Working))
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workoutId))
            ->firstOrFail();
    }
}
