<?php

namespace App\Workouts\Services;

use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Shared\Enums\SetGroupType;
use App\Users\Enums\BumpWhen;
use App\Users\Models\User;
use App\Workouts\Data\Progression\BumpProposalData;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Models\WorkoutBlockExercise;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Models\WorkoutSetGroup;
use App\Workouts\Models\WorkoutSetSegment;
use App\Workouts\Models\WorkoutWarmUpStep;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

class WorkoutService
{
    public const ROUTINE_HAS_NO_EXERCISES_ERROR = 'Unable to create a workout for a routine with no exercises';

    public const ALREADY_IN_PROGRESS_ERROR = 'You already have a workout in progress';

    public const WORKOUT_NOT_IN_PROGRESS_ERROR = 'This workout is not in progress';

    public const SET_ALREADY_COMPLETED_ERROR = 'Completed sets cannot be removed';

    public const SET_ALREADY_LOGGED_ERROR = 'This set is already logged';

    public const CANNOT_REMOVE_LAST_WORKING_SET_ERROR = 'At least one working set is required';

    public const NOTHING_TO_SKIP_IN_BLOCK_ERROR = 'Nothing left to skip in this block';

    public const WORKING_SET_GROUP_MISSING_ERROR = 'This block has no working sets';

    public const DROPSET_REQUIRES_SEGMENTS_ERROR = 'A dropset requires at least two segments';

    public const PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR = 'This set is a dropset and must be logged with segments';

    public const CANNOT_PROMOTE_COMPLETED_SET_ERROR = 'Completed sets cannot be promoted to a dropset';

    public const CANNOT_PROMOTE_WARM_UP_ERROR = 'Only working sets can be promoted to a dropset';

    public const CANNOT_PROMOTE_SUPERSET_ERROR = 'Dropsets are not supported on supersets';

    public const ALREADY_A_DROPSET_ERROR = 'This set is already a dropset';

    public function __construct(
        private readonly WorkoutProgressionService $progressionService,
    ) {}

    /**
     * @throws WorkoutServiceException
     */
    public function createWorkout(Routine $routine, WorkoutMode $mode = WorkoutMode::Standard): Workout
    {
        $routine->load([
            'user',
            'blocks.blockExercises.exercise',
            'blocks.setGroups.warmUpSteps',
            'blocks.setGroups.dropsetSegments',
        ]);

        $hasExercises = $routine->blocks->contains(
            fn (RoutineBlock $block) => $block->blockExercises->isNotEmpty()
        );

        if (! $hasExercises) {
            throw new WorkoutServiceException(self::ROUTINE_HAS_NO_EXERCISES_ERROR);
        }

        try {
            return DB::transaction(function () use ($routine, $mode): Workout {
                // Serialize starts per user so concurrent requests cannot both pass the check.
                User::query()->whereKey($routine->user_id)->lockForUpdate()->firstOrFail();

                if ($this->inProgressFor($routine->user) !== null) {
                    throw new WorkoutServiceException(self::ALREADY_IN_PROGRESS_ERROR);
                }

                $workout = Workout::create([
                    'user_id' => $routine->user_id,
                    'routine_id' => $routine->id,
                    'mode' => $mode,
                    'bump_when' => $routine->user->bump_when_default ?? BumpWhen::AnySet,
                    'status' => WorkoutStatus::InProgress,
                    'started_at' => now(),
                ]);

                $weightFactor = $mode === WorkoutMode::Deload ? (float) $routine->deload_weight_factor : 1.0;
                $repsFactor = $mode === WorkoutMode::Deload ? (float) $routine->deload_reps_factor : 1.0;

                foreach ($routine->blocks as $routineBlock) {
                    $workoutBlock = WorkoutBlock::create([
                        'workout_id' => $workout->id,
                        'position' => $routineBlock->position,
                        'is_superset' => $routineBlock->is_superset,
                        'has_setup_after' => $routineBlock->has_setup_after,
                        'has_setup_after_warm_up' => $routineBlock->has_setup_after_warm_up,
                    ]);

                    foreach ($routineBlock->blockExercises as $routineBlockExercise) {
                        WorkoutBlockExercise::create([
                            'workout_block_id' => $workoutBlock->id,
                            'exercise_id' => $routineBlockExercise->exercise_id,
                            'position' => $routineBlockExercise->position,
                            'exercise_name' => $routineBlockExercise->exercise->getName(),
                            'equipment' => $routineBlockExercise->exercise->equipment,
                            'working_weight_g' => (int) round($routineBlockExercise->working_weight_g * $weightFactor),
                            'prescribed_reps' => max(1, (int) round($routineBlockExercise->prescribed_reps * $repsFactor)),
                            'achievement_floor' => $routineBlockExercise->achievement_floor_override
                                ?? $routine->user->achievement_floor_default,
                            'progression_target' => $routineBlockExercise->prescribed_reps,
                        ]);
                    }

                    $workoutBlock->load('blockExercises');

                    foreach ($routineBlock->setGroups as $routineSetGroup) {
                        $workoutSetGroup = WorkoutSetGroup::create([
                            'workout_block_id' => $workoutBlock->id,
                            'type' => $routineSetGroup->type,
                            'set_count' => $routineSetGroup->set_count,
                            'rest_seconds' => $routineSetGroup->rest_seconds,
                        ]);

                        foreach ($routineSetGroup->warmUpSteps as $warmUpStep) {
                            WorkoutWarmUpStep::create([
                                'workout_set_group_id' => $workoutSetGroup->id,
                                'position' => $warmUpStep->position,
                                'percent_of_working' => $warmUpStep->percent_of_working,
                                'reps' => $warmUpStep->reps,
                                'has_setup_after' => $warmUpStep->has_setup_after,
                            ]);
                        }

                        $segmentsByIndex = $routineSetGroup->dropsetSegments
                            ->groupBy('set_index');

                        for ($setIndex = 0; $setIndex < $routineSetGroup->set_count; $setIndex++) {
                            $recipeSegments = $segmentsByIndex->get($setIndex, collect())
                                ->sortBy('position')
                                ->values();

                            foreach ($workoutBlock->blockExercises as $workoutBlockExercise) {
                                $workoutSet = WorkoutSet::create([
                                    'workout_set_group_id' => $workoutSetGroup->id,
                                    'workout_block_exercise_id' => $workoutBlockExercise->id,
                                    'set_index' => $setIndex,
                                ]);

                                if ($recipeSegments->count() < 2) {
                                    continue;
                                }

                                foreach ($recipeSegments as $segmentIndex => $recipeSegment) {
                                    WorkoutSetSegment::create([
                                        'workout_set_id' => $workoutSet->id,
                                        'position' => $segmentIndex + 1,
                                        'weight_g' => (int) round($recipeSegment->weight_g * $weightFactor),
                                    ]);
                                }
                            }
                        }
                    }
                }

                return $workout->fresh(['blocks']);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new WorkoutServiceException(self::ALREADY_IN_PROGRESS_ERROR, $exception->getCode(), previous: $exception);
        }
    }

    public function inProgressFor(User $user): ?Workout
    {
        return Workout::inProgressForUser($user);
    }

    /**
     * @param  list<int>|null  $segmentWeightGrams
     * @param  array{bar_g: int, per_side: list<array{denomination_g: int, count: int}>}|null  $plateStack
     *
     * @throws WorkoutServiceException
     */
    public function completeSet(
        WorkoutSet $set,
        int $reps,
        ?int $weightGrams = null,
        ?array $segmentWeightGrams = null,
        ?array $plateStack = null,
    ): WorkoutSet {
        $set->loadMissing(['setGroup.block.workout', 'segments']);
        $workout = $set->setGroup->block->workout;

        if ($workout->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }

        if ($set->completed_at !== null) {
            throw new WorkoutServiceException(self::SET_ALREADY_LOGGED_ERROR);
        }

        $isPlannedDropset = $set->isDropset();
        $hasSegments = $segmentWeightGrams !== null && count($segmentWeightGrams) >= 2;

        if ($isPlannedDropset && ! $hasSegments) {
            throw new WorkoutServiceException(self::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        if ($hasSegments) {
            return $this->completeDropset($set, $reps, $segmentWeightGrams);
        }

        if ($weightGrams === null) {
            throw new WorkoutServiceException(self::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        $set->reps = $reps;
        $set->weight_g = $weightGrams;
        $set->plate_stack = $set->setGroup->type === SetGroupType::Working ? $plateStack : null;
        $set->completed_at = now();
        $set->save();

        return $set->fresh(['segments']);
    }

    /**
     * @param  list<int>  $segmentWeightGrams
     *
     * @throws WorkoutServiceException
     */
    public function completeDropset(WorkoutSet $set, int $reps, array $segmentWeightGrams): WorkoutSet
    {
        if (count($segmentWeightGrams) < 2) {
            throw new WorkoutServiceException(self::DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        return DB::transaction(function () use ($set, $reps, $segmentWeightGrams): WorkoutSet {
            $set->replaceSegments($segmentWeightGrams);

            $set->reps = $reps;
            $set->weight_g = null;
            $set->plate_stack = null;
            $set->completed_at = now();
            $set->save();

            return $set->fresh(['segments']);
        });
    }

    /**
     * @param  list<int>  $segmentWeightGrams
     *
     * @throws WorkoutServiceException
     */
    public function promoteToDropset(WorkoutSet $set, array $segmentWeightGrams): WorkoutSet
    {
        $set->loadMissing(['setGroup.block.workout', 'segments']);

        $workout = $set->setGroup->block->workout;

        if ($workout->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }

        if ($set->completed_at !== null) {
            throw new WorkoutServiceException(self::CANNOT_PROMOTE_COMPLETED_SET_ERROR);
        }

        if ($set->setGroup->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(self::CANNOT_PROMOTE_WARM_UP_ERROR);
        }

        if ($set->setGroup->block->is_superset) {
            throw new WorkoutServiceException(self::CANNOT_PROMOTE_SUPERSET_ERROR);
        }

        if ($set->isDropset()) {
            throw new WorkoutServiceException(self::ALREADY_A_DROPSET_ERROR);
        }

        if (count($segmentWeightGrams) < 2) {
            throw new WorkoutServiceException(self::DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        return DB::transaction(function () use ($set, $segmentWeightGrams): WorkoutSet {
            $set->replaceSegments($segmentWeightGrams);

            return $set->fresh(['segments']);
        });
    }

    /**
     * @throws WorkoutServiceException
     */
    public function addWorkingSet(WorkoutBlock $block): WorkoutBlock
    {
        $block->loadMissing(['workout', 'blockExercises', 'workingSetGroup.sets']);

        if ($block->workout->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }

        $workingGroup = $block->workingSetGroup;

        if ($workingGroup === null) {
            throw new WorkoutServiceException(self::WORKING_SET_GROUP_MISSING_ERROR);
        }

        return DB::transaction(function () use ($block, $workingGroup): WorkoutBlock {
            $nextIndex = (int) $workingGroup->sets->max('set_index') + 1;

            foreach ($block->blockExercises as $exercise) {
                WorkoutSet::create([
                    'workout_set_group_id' => $workingGroup->id,
                    'workout_block_exercise_id' => $exercise->id,
                    'set_index' => $nextIndex,
                ]);
            }

            $workingGroup->set_count += 1;
            $workingGroup->save();

            return $block->fresh(['blockExercises', 'setGroups.sets']);
        });
    }

    /**
     * @throws WorkoutServiceException
     */
    public function removeWorkingSetRound(WorkoutSet $set): void
    {
        $set->loadMissing(['setGroup.block.workout', 'setGroup.sets']);

        $group = $set->setGroup;
        $workout = $group->block->workout;

        if ($workout->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }

        if ($group->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(self::WORKING_SET_GROUP_MISSING_ERROR);
        }

        $removedIndex = $set->set_index;
        $roundSets = $group->sets->where('set_index', $removedIndex);

        if ($roundSets->contains(fn (WorkoutSet $roundSet): bool => $roundSet->completed_at !== null)) {
            throw new WorkoutServiceException(self::SET_ALREADY_COMPLETED_ERROR);
        }

        if ($group->set_count <= 1) {
            throw new WorkoutServiceException(self::CANNOT_REMOVE_LAST_WORKING_SET_ERROR);
        }

        DB::transaction(function () use ($group, $roundSets, $removedIndex): void {
            foreach ($roundSets as $roundSet) {
                $roundSet->delete();
            }

            WorkoutSet::query()
                ->where('workout_set_group_id', $group->id)
                ->where('set_index', '>', $removedIndex)
                ->decrement('set_index');

            $group->set_count = max(1, $group->set_count - 1);
            $group->save();
        });
    }

    /**
     * Delete every incomplete set in the block (warm-up + working). Logged sets stay.
     * Working / warm-up set_count may become 0.
     *
     * @throws WorkoutServiceException
     */
    public function skipRestOfBlock(WorkoutBlock $block): void
    {
        $block->loadMissing(['workout', 'setGroups.sets']);

        if ($block->workout->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }

        $hasIncomplete = $block->setGroups
            ->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
            ->contains(fn (WorkoutSet $set): bool => $set->completed_at === null);

        if (! $hasIncomplete) {
            throw new WorkoutServiceException(self::NOTHING_TO_SKIP_IN_BLOCK_ERROR);
        }

        DB::transaction(function () use ($block): void {
            foreach ($block->setGroups as $group) {
                $incompleteIds = $group->sets
                    ->filter(fn (WorkoutSet $set): bool => $set->completed_at === null)
                    ->pluck('id');

                if ($incompleteIds->isNotEmpty()) {
                    WorkoutSet::query()->whereIn('id', $incompleteIds)->delete();
                }

                $remaining = WorkoutSet::query()
                    ->where('workout_set_group_id', $group->id)
                    ->orderBy('set_index')
                    ->orderBy('id')
                    ->get();

                $oldIndexes = $remaining->pluck('set_index')->unique()->sort()->values();
                $indexMap = [];
                foreach ($oldIndexes as $newIndex => $oldIndex) {
                    $indexMap[(int) $oldIndex] = $newIndex;
                }

                $needsRemap = $oldIndexes->contains(fn (mixed $oldIndex, int $newIndex): bool => (int) $oldIndex !== $newIndex);

                if ($needsRemap) {
                    $offset = ((int) $oldIndexes->max()) + 1;

                    foreach ($remaining as $set) {
                        $set->set_index += $offset;
                        $set->save();
                    }

                    foreach ($remaining as $set) {
                        $originalIndex = $set->set_index - $offset;
                        $set->set_index = $indexMap[$originalIndex];
                        $set->save();
                    }
                }

                $group->set_count = $oldIndexes->count();
                $group->save();
            }
        });
    }

    /**
     * @return DataCollection<int, BumpProposalData>
     *
     * @throws WorkoutServiceException
     */
    public function finishWorkout(Workout $workout): DataCollection
    {
        return DB::transaction(function () use ($workout): DataCollection {
            $locked = $this->lockInProgressWorkout($workout);

            $locked->status = WorkoutStatus::Finished;
            $locked->finished_at = now();
            $locked->save();

            $workout->status = $locked->status;
            $workout->finished_at = $locked->finished_at;

            return $this->progressionService->applyCarryForwardAndCollectBumps($locked);
        });
    }

    /**
     * @throws WorkoutServiceException
     */
    public function discardWorkout(Workout $workout): void
    {
        DB::transaction(function () use ($workout): void {
            $locked = $this->lockInProgressWorkout($workout);

            $locked->status = WorkoutStatus::Discarded;
            $locked->save();

            $workout->status = $locked->status;
        });
    }

    /**
     * @throws WorkoutServiceException
     */
    private function lockInProgressWorkout(Workout $workout): Workout
    {
        $locked = Workout::query()->whereKey($workout->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }

        return $locked;
    }
}
