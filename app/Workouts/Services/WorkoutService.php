<?php

namespace App\Workouts\Services;

use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Shared\Enums\SetGroupType;
use App\Users\Enums\ProgressionStyle;
use App\Users\Enums\ProgressiveMidBlock;
use App\Users\Models\User;
use App\Workouts\Data\History\StoreHistoricalBlockData;
use App\Workouts\Data\History\StoreHistoricalSetData;
use App\Workouts\Data\History\StoreHistoricalWorkoutData;
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
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
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

    public const NOTHING_TO_SKIP_IN_BLOCK_ERROR = 'Nothing left to skip in this group';

    public const WORKING_SET_GROUP_MISSING_ERROR = 'This group has no working sets';

    public const DROPSET_REQUIRES_SEGMENTS_ERROR = 'A dropset requires at least two segments';

    public const PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR = 'This set is a dropset and must be logged with segments';

    public const CANNOT_PROMOTE_COMPLETED_SET_ERROR = 'Completed sets cannot be promoted to a dropset';

    public const CANNOT_PROMOTE_WARM_UP_ERROR = 'Only working sets can be promoted to a dropset';

    public const CANNOT_PROMOTE_SUPERSET_ERROR = 'Dropsets are not supported on supersets';

    public const ALREADY_A_DROPSET_ERROR = 'This set is already a dropset';

    public const CANNOT_DEMOTE_COMPLETED_SET_ERROR = 'Completed sets cannot be demoted from a dropset';

    public const CANNOT_DEMOTE_WARM_UP_ERROR = 'Only working sets can be demoted from a dropset';

    public const NOT_A_DROPSET_ERROR = 'This set is not a dropset';

    public const AD_HOC_EXERCISE_NOT_AVAILABLE_ERROR = 'This exercise is not available to you';

    public const AD_HOC_BLOCK_ONLY_ERROR = 'Only ad-hoc blocks can be removed this way';

    public const AD_HOC_BLOCK_HAS_LOGGED_SETS_ERROR = 'An ad-hoc block with logged sets cannot be removed';

    public const HISTORICAL_NO_BLOCKS_ERROR = 'Add at least one group to log a historical workout';

    public const HISTORICAL_UNKNOWN_BLOCK_ERROR = 'One or more groups are not part of this routine';

    public const HISTORICAL_SET_MISMATCH_ERROR = 'Logged sets must cover every working set in the kept groups';

    public const HISTORICAL_FUTURE_FINISHED_AT_ERROR = 'Finished time cannot be in the future';

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
            'blocks.blockExercises.deloadExercise',
            'blocks.blockExercises.exerciseProfile',
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
                    'progression_style' => $routine->user->progression_style_default ?? ProgressionStyle::StraightSets,
                    'progressive_mid_block' => $routine->user->progressive_mid_block_default ?? ProgressiveMidBlock::Ask,
                    'status' => WorkoutStatus::InProgress,
                    'started_at' => now(),
                ]);

                $this->snapshotRoutineOntoWorkout($workout, $routine, $mode);

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
     * Create a finished workout without going through Play. Does not conflict with an in-progress session.
     *
     * @return array{0: Workout, 1: DataCollection<int, BumpProposalData>}
     *
     * @throws WorkoutServiceException
     */
    public function createHistoricalWorkout(Routine $routine, StoreHistoricalWorkoutData $data): array
    {
        $routine->load([
            'user',
            'blocks.blockExercises.exercise',
            'blocks.blockExercises.deloadExercise',
            'blocks.setGroups.warmUpSteps',
            'blocks.setGroups.dropsetSegments',
        ]);

        $hasExercises = $routine->blocks->contains(
            fn (RoutineBlock $block) => $block->blockExercises->isNotEmpty()
        );

        if (! $hasExercises) {
            throw new WorkoutServiceException(self::ROUTINE_HAS_NO_EXERCISES_ERROR);
        }

        $finishedAt = $data->finishedAt;
        if ($finishedAt->isFuture()) {
            throw new WorkoutServiceException(self::HISTORICAL_FUTURE_FINISHED_AT_ERROR);
        }

        /** @var list<StoreHistoricalBlockData> $blocksPayload */
        $blocksPayload = array_values($data->blocks->all());

        if ($blocksPayload === []) {
            throw new WorkoutServiceException(self::HISTORICAL_NO_BLOCKS_ERROR);
        }

        $routineBlocksByPosition = $routine->blocks->keyBy('position');
        $positions = [];
        $workingSetCountByPosition = [];

        foreach ($blocksPayload as $blockData) {
            if (! $routineBlocksByPosition->has($blockData->position)) {
                throw new WorkoutServiceException(self::HISTORICAL_UNKNOWN_BLOCK_ERROR);
            }

            $positions[] = $blockData->position;
            $workingSetCountByPosition[$blockData->position] = $blockData->workingSetCount;
        }

        $mode = $data->modeOrDefault();

        return DB::transaction(function () use (
            $routine,
            $mode,
            $finishedAt,
            $positions,
            $workingSetCountByPosition,
            $blocksPayload,
            $routineBlocksByPosition,
        ): array {
            $workout = Workout::create([
                'user_id' => $routine->user_id,
                'routine_id' => $routine->id,
                'mode' => $mode,
                'progression_style' => $routine->user->progression_style_default ?? ProgressionStyle::StraightSets,
                'progressive_mid_block' => $routine->user->progressive_mid_block_default ?? ProgressiveMidBlock::Ask,
                'status' => WorkoutStatus::Finished,
                'started_at' => $finishedAt,
                'finished_at' => $finishedAt,
            ]);

            $this->snapshotRoutineOntoWorkout(
                $workout,
                $routine,
                $mode,
                $positions,
                $workingSetCountByPosition,
            );

            $workout->load([
                'blocks.blockExercises',
                'blocks.setGroups.sets.segments',
            ]);

            $this->applyHistoricalSetLogs($workout, $blocksPayload, $routineBlocksByPosition, $finishedAt);

            $bumps = $workout->isEligibleForProgressionReEval()
                ? $this->progressionService->applyCarryForwardAndCollectBumps($workout)
                : BumpProposalData::collect([], DataCollection::class);

            return [$workout->fresh(['blocks']), $bumps];
        });
    }

    /**
     * @param  list<StoreHistoricalBlockData>  $blocksPayload
     * @param  Collection<int, RoutineBlock>  $routineBlocksByPosition
     *
     * @throws WorkoutServiceException
     */
    private function applyHistoricalSetLogs(
        Workout $workout,
        array $blocksPayload,
        $routineBlocksByPosition,
        CarbonInterface $finishedAt,
    ): void {
        $workoutBlocksByPosition = $workout->blocks->keyBy('position');

        foreach ($blocksPayload as $blockData) {
            /** @var WorkoutBlock $workoutBlock */
            $workoutBlock = $workoutBlocksByPosition->get($blockData->position);
            /** @var RoutineBlock $routineBlock */
            $routineBlock = $routineBlocksByPosition->get($blockData->position);

            $exerciseCount = $routineBlock->blockExercises->count();
            $expectedSetRows = $blockData->workingSetCount * $exerciseCount;

            /** @var list<StoreHistoricalSetData> $sets */
            $sets = array_values($blockData->sets->all());

            if (count($sets) !== $expectedSetRows) {
                throw new WorkoutServiceException(self::HISTORICAL_SET_MISMATCH_ERROR);
            }

            $exercisesById = $workoutBlock->blockExercises->keyBy('id');
            $workingGroup = $workoutBlock->setGroups->first(
                fn (WorkoutSetGroup $group): bool => $group->type === SetGroupType::Working
            );

            if ($workingGroup === null) {
                throw new WorkoutServiceException(self::WORKING_SET_GROUP_MISSING_ERROR);
            }

            $this->applyHistoricalGroupSetLogs($workingGroup, $exercisesById, $sets, $finishedAt);

            $warmUpGroup = $workoutBlock->setGroups->first(
                fn (WorkoutSetGroup $group): bool => $group->type === SetGroupType::WarmUp
            );

            /** @var list<StoreHistoricalSetData> $warmUpSets */
            $warmUpSets = $blockData->warmUpSets !== null
                ? array_values($blockData->warmUpSets->all())
                : [];

            if ($warmUpGroup !== null && $warmUpSets !== []) {
                if (count($warmUpSets) !== $warmUpGroup->sets->count()) {
                    throw new WorkoutServiceException(self::HISTORICAL_SET_MISMATCH_ERROR);
                }

                $this->applyHistoricalGroupSetLogs($warmUpGroup, $exercisesById, $warmUpSets, $finishedAt);
            }
        }
    }

    /**
     * @param  Collection<int, WorkoutBlockExercise>  $exercisesById
     * @param  list<StoreHistoricalSetData>  $sets
     *
     * @throws WorkoutServiceException
     */
    private function applyHistoricalGroupSetLogs(
        WorkoutSetGroup $group,
        $exercisesById,
        array $sets,
        CarbonInterface $finishedAt,
    ): void {
        $setsByKey = $group->sets->keyBy(
            function (WorkoutSet $set) use ($exercisesById): string {
                /** @var WorkoutBlockExercise|null $exercise */
                $exercise = $exercisesById->get($set->workout_block_exercise_id);

                return sprintf('%d:%d', $exercise?->position ?? 0, $set->set_index);
            }
        );

        $seen = [];

        foreach ($sets as $setData) {
            $key = sprintf('%d:%d', $setData->exercisePosition, $setData->setIndex);

            if (isset($seen[$key]) || ! $setsByKey->has($key)) {
                throw new WorkoutServiceException(self::HISTORICAL_SET_MISMATCH_ERROR);
            }

            $seen[$key] = true;

            /** @var WorkoutSet $workoutSet */
            $workoutSet = $setsByKey->get($key);
            $this->recordHistoricalSet($workoutSet, $setData, $finishedAt);
        }
    }

    /**
     * @throws WorkoutServiceException
     */
    private function recordHistoricalSet(
        WorkoutSet $set,
        StoreHistoricalSetData $data,
        CarbonInterface $finishedAt,
    ): void {
        $segmentWeightGrams = $data->segmentWeightGrams();
        $hasSegments = $segmentWeightGrams !== null && count($segmentWeightGrams) >= 2;
        $isPlannedDropset = $set->isDropset();

        if ($isPlannedDropset && ! $hasSegments) {
            throw new WorkoutServiceException(self::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        if ($hasSegments) {
            if (count($segmentWeightGrams) < 2) {
                throw new WorkoutServiceException(self::DROPSET_REQUIRES_SEGMENTS_ERROR);
            }

            $set->replaceSegments($segmentWeightGrams);
            $set->reps = $data->reps;
            $set->weight_g = null;
            $set->plate_stack = null;
            $set->completed_at = $finishedAt;
            $set->save();

            return;
        }

        $weightGrams = $data->weightGrams();

        if ($weightGrams === null) {
            throw new WorkoutServiceException(self::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        $set->replaceSegments([]);
        $set->reps = $data->reps;
        $set->weight_g = $weightGrams;
        $set->plate_stack = null;
        $set->completed_at = $finishedAt;
        $set->save();
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
        $this->assertInProgress($set->setGroup->block->workout);

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
    private function completeDropset(WorkoutSet $set, int $reps, array $segmentWeightGrams): WorkoutSet
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

        $this->assertInProgress($set->setGroup->block->workout);

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
    public function demoteFromDropset(WorkoutSet $set): WorkoutSet
    {
        $set->loadMissing(['setGroup.block.workout']);
        $set->load('segments');

        $this->assertInProgress($set->setGroup->block->workout);

        if ($set->completed_at !== null) {
            throw new WorkoutServiceException(self::CANNOT_DEMOTE_COMPLETED_SET_ERROR);
        }

        if ($set->setGroup->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(self::CANNOT_DEMOTE_WARM_UP_ERROR);
        }

        if (! $set->isDropset()) {
            throw new WorkoutServiceException(self::NOT_A_DROPSET_ERROR);
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
                throw new WorkoutServiceException(self::AD_HOC_EXERCISE_NOT_AVAILABLE_ERROR);
            }

            $previousBlock = $locked->blocks->sortByDesc('position')->first();
            $position = ((int) ($previousBlock?->position ?? 0)) + 1;
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
            throw new WorkoutServiceException(self::AD_HOC_BLOCK_ONLY_ERROR);
        }

        if ($block->setGroups->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
            ->contains(fn (WorkoutSet $set): bool => $set->completed_at !== null)) {
            throw new WorkoutServiceException(self::AD_HOC_BLOCK_HAS_LOGGED_SETS_ERROR);
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
                throw new WorkoutServiceException(self::AD_HOC_BLOCK_ONLY_ERROR);
            }

            if ($lockedBlock->setGroups->flatMap(fn (WorkoutSetGroup $group) => $group->sets)
                ->contains(fn (WorkoutSet $set): bool => $set->completed_at !== null)) {
                throw new WorkoutServiceException(self::AD_HOC_BLOCK_HAS_LOGGED_SETS_ERROR);
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

        $this->assertInProgress($block->workout);

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
    private function assertInProgress(Workout $workout): void
    {
        if ($workout->status !== WorkoutStatus::InProgress) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_IN_PROGRESS_ERROR);
        }
    }

    /**
     * @throws WorkoutServiceException
     */
    private function lockInProgressWorkout(Workout $workout): Workout
    {
        $locked = Workout::query()->whereKey($workout->id)->lockForUpdate()->firstOrFail();

        $this->assertInProgress($locked);

        return $locked;
    }

    /**
     * Snapshot routine structure onto a workout. Optional block filter and per-block working set counts
     * support historical creates (skip blocks / +/- sets) without mutating after the fact.
     *
     * @param  list<int>|null  $routineBlockPositions  null = all blocks; empty not allowed by callers
     * @param  array<int, int>  $workingSetCountByPosition  routine block position → working set_count override
     */
    public function snapshotRoutineOntoWorkout(
        Workout $workout,
        Routine $routine,
        WorkoutMode $mode,
        ?array $routineBlockPositions = null,
        array $workingSetCountByPosition = [],
    ): void {
        $routine->loadMissing([
            'user',
            'blocks.blockExercises.exercise',
            'blocks.blockExercises.deloadExercise',
            'blocks.setGroups.warmUpSteps',
            'blocks.setGroups.dropsetSegments',
        ]);

        $isDeload = $mode === WorkoutMode::Deload;
        $weightFactor = $isDeload ? (float) $routine->deload_weight_factor : 1.0;
        $repsFactor = $isDeload ? (float) $routine->deload_reps_factor : 1.0;

        $blocks = $routine->blocks;
        if ($routineBlockPositions !== null) {
            $allowed = array_flip($routineBlockPositions);
            $blocks = $blocks->filter(
                fn (RoutineBlock $block): bool => array_key_exists($block->position, $allowed)
            )->values();
        }

        foreach ($blocks as $routineBlock) {
            $workoutBlock = WorkoutBlock::create([
                'workout_id' => $workout->id,
                'position' => $routineBlock->position,
                'is_superset' => $routineBlock->is_superset,
                'has_setup_after' => $routineBlock->has_setup_after,
                // Deload omits warm-ups; setup-after-warm-up would never fire.
                'has_setup_after_warm_up' => $isDeload ? false : $routineBlock->has_setup_after_warm_up,
            ]);

            /** @var array<int, true> $skipDropsetsByWorkoutExerciseId */
            $skipDropsetsByWorkoutExerciseId = [];

            foreach ($routineBlock->blockExercises as $routineBlockExercise) {
                $useAlternate = $isDeload && $routineBlockExercise->hasDeloadAlternate();
                $sourceExercise = $useAlternate
                    ? $routineBlockExercise->deloadExercise
                    : $routineBlockExercise->exercise;
                // Alternate weight is already the deload load — do not apply the recipe weight factor.
                $workingWeightG = $useAlternate
                    ? (int) $routineBlockExercise->deload_working_weight_g
                    : (int) round($routineBlockExercise->working_weight_g * $weightFactor);

                $achievementFloor = $routineBlockExercise->floor_is_derived === true
                    ? max(1, $routineBlockExercise->prescribed_reps - 2)
                    : ($routineBlockExercise->achievement_floor_override
                        ?? $routine->user->achievement_floor_default);

                $workoutBlockExercise = WorkoutBlockExercise::create([
                    'workout_block_id' => $workoutBlock->id,
                    'exercise_id' => $sourceExercise->id,
                    'position' => $routineBlockExercise->position,
                    'exercise_name' => $sourceExercise->getName(),
                    'equipment' => $sourceExercise->equipment,
                    'working_weight_g' => $workingWeightG,
                    'prescribed_reps' => max(1, (int) round($routineBlockExercise->prescribed_reps * $repsFactor)),
                    'achievement_floor' => $achievementFloor,
                    'progression_target' => $routineBlockExercise->prescribed_reps,
                ]);

                if ($useAlternate) {
                    $skipDropsetsByWorkoutExerciseId[$workoutBlockExercise->id] = true;
                }
            }

            $workoutBlock->load('blockExercises');

            foreach ($routineBlock->setGroups as $routineSetGroup) {
                if ($isDeload && $routineSetGroup->type === SetGroupType::WarmUp) {
                    continue;
                }

                $setCount = $routineSetGroup->set_count;
                if (
                    $routineSetGroup->type === SetGroupType::Working
                    && array_key_exists((string) $routineBlock->position, $workingSetCountByPosition)
                ) {
                    $setCount = $workingSetCountByPosition[$routineBlock->position];
                }

                $workoutSetGroup = WorkoutSetGroup::create([
                    'workout_block_id' => $workoutBlock->id,
                    'type' => $routineSetGroup->type,
                    'set_count' => $setCount,
                    'rest_seconds' => $routineSetGroup->rest_seconds,
                ]);

                foreach ($routineSetGroup->warmUpSteps as $warmUpStep) {
                    WorkoutWarmUpStep::create([
                        'workout_set_group_id' => $workoutSetGroup->id,
                        'position' => $warmUpStep->position,
                        'weight_mode' => $warmUpStep->weight_mode,
                        'percent_of_working' => $warmUpStep->percent_of_working,
                        'weight_g' => $warmUpStep->weight_g,
                        'reps' => $warmUpStep->reps,
                        'has_setup_after' => $warmUpStep->has_setup_after,
                    ]);
                }

                $segmentsByIndex = $routineSetGroup->dropsetSegments
                    ->groupBy('set_index');

                for ($setIndex = 0; $setIndex < $setCount; $setIndex++) {
                    $recipeSegments = $segmentsByIndex->get($setIndex, collect())
                        ->sortBy('position')
                        ->values();

                    foreach ($workoutBlock->blockExercises as $workoutBlockExercise) {
                        $workoutSet = WorkoutSet::create([
                            'workout_set_group_id' => $workoutSetGroup->id,
                            'workout_block_exercise_id' => $workoutBlockExercise->id,
                            'set_index' => $setIndex,
                        ]);

                        if (
                            $recipeSegments->count() < 2
                            || isset($skipDropsetsByWorkoutExerciseId[$workoutBlockExercise->id])
                        ) {
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
    }
}
