<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlockExercise;
use App\Users\Models\User;
use App\Workouts\Models\Workout;
use App\Workouts\Services\WorkoutService;

/**
 * Requires {@see UserHelper} for default `$user` resolution.
 */
trait CreatesPlayableWorkout
{
    use SeedsPlayableRoutineBlock;

    protected function createPlayableWorkout(
        ?User $user = null,
        int $setCount = 1,
        bool $loadBlocks = false,
        ?int $restSeconds = 90,
        int $workingWeightG = 80000,
        int $prescribedReps = 6,
        ?int $progressionTarget = null,
        ?int $achievementFloor = null,
    ): Workout {
        $user ??= $this->user;

        $routine = Routine::factory()->withUser($user)->create();
        $this->seedPlayableRoutineBlock(
            $routine,
            setCount: $setCount,
            restSeconds: $restSeconds,
            workingWeightG: $workingWeightG,
            prescribedReps: $prescribedReps,
            progressionTarget: $progressionTarget,
            achievementFloor: $achievementFloor,
        );

        $workout = app(WorkoutService::class)->createWorkout($routine);

        return $loadBlocks ? $workout->load('blocks') : $workout;
    }

    /**
     * @return array{0: Workout, 1: RoutineBlockExercise}
     */
    protected function createFinishedEligibleWorkout(
        ?User $user = null,
        int $reps = 6,
        int $weightGrams = 80000,
    ): array {
        $user ??= $this->user;

        $user->update([
            'progression_target_default' => 6,
            'achievement_floor_default' => 4,
        ]);

        $routine = Routine::factory()->withUser($user)->create();
        [, $routineExercise] = $this->seedPlayableRoutineBlock(
            $routine,
            setCount: 1,
            restSeconds: 90,
            workingWeightG: 80000,
            prescribedReps: 6,
            progressionTarget: 6,
            achievementFloor: 4,
        );

        $workout = app(WorkoutService::class)->createWorkout($routine);
        app(WorkoutService::class)->completeSet(
            $this->firstWorkingSet($workout->id),
            reps: $reps,
            weightGrams: $weightGrams,
        );

        return [$workout->fresh(), $routineExercise];
    }

    /**
     * @return array{0: Workout, 1: RoutineBlockExercise, 2: Routine}
     */
    protected function createFinishedWorkout(
        ?User $user = null,
        int $reps = 6,
        int $weightGrams = 80000,
    ): array {
        $user ??= $this->user;

        $user->update([
            'progression_target_default' => 6,
            'achievement_floor_default' => 4,
        ]);

        $routine = Routine::factory()->withUser($user)->create();
        [, $routineExercise] = $this->seedPlayableRoutineBlock(
            $routine,
            setCount: 1,
            restSeconds: 90,
            workingWeightG: 80000,
            prescribedReps: 6,
            progressionTarget: 6,
            achievementFloor: 4,
        );

        $workoutService = app(WorkoutService::class);
        $workout = $workoutService->createWorkout($routine);
        $workoutService->completeSet(
            $this->firstWorkingSet($workout->id),
            reps: $reps,
            weightGrams: $weightGrams,
        );
        $workoutService->finishWorkout($workout);

        return [$workout->fresh(), $routineExercise, $routine];
    }
}
