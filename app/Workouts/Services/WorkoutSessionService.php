<?php

namespace App\Workouts\Services;

use App\Exercises\Models\Exercise;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Models\WorkoutBlockExercise;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Models\WorkoutSetGroup;
use Illuminate\Support\Facades\DB;

final readonly class WorkoutSessionService
{
    public function __construct(
        private WorkoutSetLogger $setLogger,
    ) {}

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
        $this->assertInProgress($set->setGroup->block->workout);

        if ($set->completed_at !== null) {
            throw new WorkoutServiceException(WorkoutService::SET_ALREADY_LOGGED_ERROR);
        }

        $isPlannedDropset = $set->isDropset();
        $hasSegments = $segmentWeightGrams !== null && count($segmentWeightGrams) >= 2;

        if ($isPlannedDropset && ! $hasSegments) {
            throw new WorkoutServiceException(WorkoutService::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        if ($hasSegments) {
            return DB::transaction(function () use ($set, $reps, $segmentWeightGrams): WorkoutSet {
                $this->setLogger->applyLoggedValues(
                    $set,
                    $reps,
                    segmentWeightGrams: $segmentWeightGrams,
                    completedAt: now(),
                );
                $set->save();

                return $set->fresh(['segments']);
            });
        }

        $this->setLogger->applyLoggedValues(
            $set,
            $reps,
            weightGrams: $weightGrams,
            plateStack: $plateStack,
            completedAt: now(),
        );
        $set->save();

        return $set->fresh(['segments']);
    }

    /**
     * @param  list<int>  $segmentWeightGrams
     *
     * @throws WorkoutServiceException
     */
    public function promoteToDropset(WorkoutSet $set, array $segmentWeightGrams): WorkoutSet
    {
        $set->loadMissing(['setGroup.block.workout', 'segments']);

        $this->assertInProgress($set->setGroup->block->workout);

        if ($set->completed_at !== null) {
            throw new WorkoutServiceException(WorkoutService::CANNOT_PROMOTE_COMPLETED_SET_ERROR);
        }

        if ($set->setGroup->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(WorkoutService::CANNOT_PROMOTE_WARM_UP_ERROR);
        }

        if ($set->setGroup->block->is_superset) {
            throw new WorkoutServiceException(WorkoutService::CANNOT_PROMOTE_SUPERSET_ERROR);
        }

        if ($set->isDropset()) {
            throw new WorkoutServiceException(WorkoutService::ALREADY_A_DROPSET_ERROR);
        }

        if (count($segmentWeightGrams) < 2) {
            throw new WorkoutServiceException(WorkoutService::DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        return DB::transaction(function () use ($set, $segmentWeightGrams): WorkoutSet {
            $set->replaceSegments($segmentWeightGrams);

            return $set->fresh(['segments']);
        });
    }

    /**
     * @throws WorkoutServiceException
     */
    public function demoteFromDropset(WorkoutSet $set): WorkoutSet
    {
        $set->loadMissing(['setGroup.block.workout']);
        $set->load('segments');

        $this->assertInProgress($set->setGroup->block->workout);

        if ($set->completed_at !== null) {
            throw new WorkoutServiceException(WorkoutService::CANNOT_DEMOTE_COMPLETED_SET_ERROR);
        }

        if ($set->setGroup->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(WorkoutService::CANNOT_DEMOTE_WARM_UP_ERROR);
        }

        if (! $set->isDropset()) {
            throw new WorkoutServiceException(WorkoutService::NOT_A_DROPSET_ERROR);
        }

        return DB::transaction(function () use ($set): WorkoutSet {
            $set->clearSegments();

            return $set->fresh(['segments']);
        });
    }

    /**
     * @throws WorkoutServiceException
     */
    public function addWorkingSet(WorkoutBlock $block): WorkoutBlock
    {
        $block->loadMissing(['workout', 'blockExercises', 'workingSetGroup.sets']);

        $this->assertInProgress($block->workout);

        $workingGroup = $block->workingSetGroup;

        if ($workingGroup === null) {
            throw new WorkoutServiceException(WorkoutService::WORKING_SET_GROUP_MISSING_ERROR);
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
     * Append a single-exercise block to the in-progress workout snapshot.
     *
     * @throws WorkoutServiceException
     */
    public function addAdHocExercise(Workout $workout, int $exerciseId): WorkoutBlock
    {
        return DB::transaction(function () use ($workout, $exerciseId): WorkoutBlock {
            $locked = $this->lockInProgressWorkout($workout);
            $locked->load(['user.defaultExerciseProfile', 'blocks']);

            $exercise = Exercise::query()
                ->forUser($locked->user)
                ->whereKey($exerciseId)
                ->first();

            if ($exercise === null) {
                throw new WorkoutServiceException(WorkoutService::AD_HOC_EXERCISE_NOT_AVAILABLE_ERROR);
            }

            $previousBlock = $locked->blocks->sortByDesc('position')->first();
            $position = ($previousBlock !== null ? (int) $previousBlock->position : 0) + 1;
            $profile = $locked->user->defaultExerciseProfile;
            $targetReps = $profile === null ? $locked->user->resolvedDefaultTargetReps() : $profile->target_reps;
            $workingRest = $profile === null ? 120 : $profile->working_rest_seconds;

            $adHocBlock = WorkoutBlock::create([
                'workout_id' => $locked->id,
                'position' => $position,
                'is_superset' => false,
                'is_ad_hoc' => true,
                'has_setup_after' => false,
                'has_setup_after_warm_up' => false,
            ]);

            $adHocExercise = WorkoutBlockExercise::create([
                'workout_block_id' => $adHocBlock->id,
                'exercise_id' => $exercise->id,
                'position' => 1,
                'exercise_name' => $exercise->getName(),
                'equipment' => $exercise->equipment,
                'working_weight_g' => 0,
                'prescribed_reps' => $targetReps,
                'achievement_floor' => null,
                'progression_target' => null,
            ]);

            $workingGroup = WorkoutSetGroup::create([
                'workout_block_id' => $adHocBlock->id,
                'type' => SetGroupType::Working,
                'set_count' => 3,
                'rest_seconds' => $workingRest,
            ]);

            for ($setIndex = 0; $setIndex < 3; $setIndex++) {
                WorkoutSet::create([
                    'workout_set_group_id' => $workingGroup->id,
                    'workout_block_exercise_id' => $adHocExercise->id,
                    'set_index' => $setIndex,
                ]);
            }

            return $adHocBlock->fresh(['blockExercises', 'setGroups.sets']);
        });
    }

    /**
     * Remove an empty ad-hoc block from an in-progress workout snapshot.
     *
     * @throws WorkoutServiceException
     */
    public function removeAdHocBlock(WorkoutBlock $block): void
    {
        $block->loadMissing(['workout', 'setGroups.sets']);

        $this->assertInProgress($block->workout);

        if (! $block->is_ad_hoc) {
            throw new WorkoutServiceException(WorkoutService::AD_HOC_BLOCK_ONLY_ERROR);
        }

        if ($block->setGroups->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
            ->contains(fn (WorkoutSet $set): bool => $set->completed_at !== null)) {
            throw new WorkoutServiceException(WorkoutService::AD_HOC_BLOCK_HAS_LOGGED_SETS_ERROR);
        }

        DB::transaction(function () use ($block): void {
            $locked = $this->lockInProgressWorkout($block->workout);
            $lockedBlock = WorkoutBlock::query()
                ->whereKey($block->id)
                ->where('workout_id', $locked->id)
                ->with('setGroups.sets')
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedBlock->is_ad_hoc) {
                throw new WorkoutServiceException(WorkoutService::AD_HOC_BLOCK_ONLY_ERROR);
            }

            if ($lockedBlock->setGroups->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
                ->contains(fn (WorkoutSet $set): bool => $set->completed_at !== null)) {
                throw new WorkoutServiceException(WorkoutService::AD_HOC_BLOCK_HAS_LOGGED_SETS_ERROR);
            }

            $lockedBlock->delete();
        });
    }

    /**
     * @throws WorkoutServiceException
     */
    public function removeWorkingSetRound(WorkoutSet $set): void
    {
        $set->loadMissing(['setGroup.block.workout', 'setGroup.sets']);

        $group = $set->setGroup;

        $this->assertInProgress($group->block->workout);

        if ($group->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(WorkoutService::WORKING_SET_GROUP_MISSING_ERROR);
        }

        $removedIndex = $set->set_index;
        $roundSets = $group->sets->where('set_index', $removedIndex);

        if ($roundSets->contains(fn (WorkoutSet $roundSet): bool => $roundSet->completed_at !== null)) {
            throw new WorkoutServiceException(WorkoutService::SET_ALREADY_COMPLETED_ERROR);
        }

        if ($group->set_count <= 1) {
            throw new WorkoutServiceException(WorkoutService::CANNOT_REMOVE_LAST_WORKING_SET_ERROR);
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

        $this->assertInProgress($block->workout);

        $hasIncomplete = $block->setGroups
            ->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
            ->contains(fn (WorkoutSet $set): bool => $set->completed_at === null);

        if (! $hasIncomplete) {
            throw new WorkoutServiceException(WorkoutService::NOTHING_TO_SKIP_IN_BLOCK_ERROR);
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
     * Park an untouched block so Play can advance to a later group (Do groups later).
     *
     * @throws WorkoutServiceException
     */
    public function parkBlockForLater(WorkoutBlock $block): void
    {
        $block->loadMissing(['workout.blocks.setGroups.sets', 'setGroups.sets']);

        $this->assertInProgress($block->workout);

        if ($block->is_parked) {
            throw new WorkoutServiceException(WorkoutService::BLOCK_ALREADY_PARKED_ERROR);
        }

        $hasLoggedSet = $block->setGroups
            ->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
            ->contains(fn (WorkoutSet $set): bool => $set->completed_at !== null);

        if ($hasLoggedSet) {
            throw new WorkoutServiceException(WorkoutService::BLOCK_ALREADY_STARTED_ERROR);
        }

        $hasLaterNonParkedIncomplete = $block->workout->blocks
            ->filter(fn (WorkoutBlock $other): bool => $other->position > $block->position && ! $other->is_parked)
            ->contains(function (WorkoutBlock $other): bool {
                $other->loadMissing('setGroups.sets');

                return $other->setGroups
                    ->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
                    ->contains(fn (WorkoutSet $set): bool => $set->completed_at === null);
            });

        if (! $hasLaterNonParkedIncomplete) {
            throw new WorkoutServiceException(WorkoutService::NO_LATER_GROUP_TO_DO_ERROR);
        }

        $block->is_parked = true;
        $block->save();
    }

    /**
     * Clear parked marks on every block (Yes encore or No thanks).
     *
     * @throws WorkoutServiceException
     */
    public function clearParkedBlocks(Workout $workout): void
    {
        $this->assertInProgress($workout);

        WorkoutBlock::query()
            ->where('workout_id', $workout->id)
            ->where('is_parked', true)
            ->update(['is_parked' => false]);
    }

    /**
     * @throws WorkoutServiceException
     */
    public function lockInProgressWorkout(Workout $workout): Workout
    {
        $locked = Workout::query()->whereKey($workout->id)->lockForUpdate()->firstOrFail();

        $this->assertInProgress($locked);

        return $locked;
    }

    /**
     * @throws WorkoutServiceException
     */
    private function assertInProgress(Workout $workout): void
    {
        if ($workout->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(WorkoutService::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }
    }
}
