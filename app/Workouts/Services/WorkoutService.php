<?php

namespace App\Workouts\Services;

use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Users\Enums\ProgressionStyle;
use App\Users\Enums\ProgressiveMidBlock;
use App\Users\Models\User;
use App\Workouts\Data\History\StoreHistoricalBlockData;
use App\Workouts\Data\History\StoreHistoricalWorkoutData;
use App\Workouts\Data\Progression\BumpProposalData;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Models\WorkoutSet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

final readonly class WorkoutService
{
    public const string ROUTINE_HAS_NO_EXERCISES_ERROR = 'Unable to create a workout for a routine with no exercises';

    public const string ALREADY_IN_PROGRESS_ERROR = 'You already have a workout in progress';

    public const string WORKOUT_NOT_IN_PROGRESS_ERROR = 'This workout is not in progress';

    public const string SET_ALREADY_COMPLETED_ERROR = 'Completed sets cannot be removed';

    public const string SET_ALREADY_LOGGED_ERROR = 'This set is already logged';

    public const string CANNOT_REMOVE_LAST_WORKING_SET_ERROR = 'At least one working set is required';

    public const string NOTHING_TO_SKIP_IN_BLOCK_ERROR = 'Nothing left to skip in this group';

    public const string BLOCK_ALREADY_STARTED_ERROR = 'Only untouched groups can be saved for later';

    public const string NO_LATER_GROUP_TO_DO_ERROR = 'There is no later group to do instead';

    public const string BLOCK_ALREADY_PARKED_ERROR = 'This group is already saved for later';

    public const string WORKING_SET_GROUP_MISSING_ERROR = 'This group has no working sets';

    public const string DROPSET_REQUIRES_SEGMENTS_ERROR = 'A dropset requires at least two segments';

    public const string PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR = 'This set is a dropset and must be logged with segments';

    public const string CANNOT_PROMOTE_COMPLETED_SET_ERROR = 'Completed sets cannot be promoted to a dropset';

    public const string CANNOT_PROMOTE_WARM_UP_ERROR = 'Only working sets can be promoted to a dropset';

    public const string CANNOT_PROMOTE_SUPERSET_ERROR = 'Dropsets are not supported on supersets';

    public const string ALREADY_A_DROPSET_ERROR = 'This set is already a dropset';

    public const string CANNOT_DEMOTE_COMPLETED_SET_ERROR = 'Completed sets cannot be demoted from a dropset';

    public const string CANNOT_DEMOTE_WARM_UP_ERROR = 'Only working sets can be demoted from a dropset';

    public const string NOT_A_DROPSET_ERROR = 'This set is not a dropset';

    public const string AD_HOC_EXERCISE_NOT_AVAILABLE_ERROR = 'This exercise is not available to you';

    public const string AD_HOC_BLOCK_ONLY_ERROR = 'Only ad-hoc blocks can be removed this way';

    public const string AD_HOC_BLOCK_HAS_LOGGED_SETS_ERROR = 'An ad-hoc block with logged sets cannot be removed';

    public const string HISTORICAL_NO_BLOCKS_ERROR = 'Add at least one group to log a historical workout';

    public const string HISTORICAL_UNKNOWN_BLOCK_ERROR = 'One or more groups are not part of this routine';

    public const string HISTORICAL_SET_MISMATCH_ERROR = 'Logged sets must cover every working set in the kept groups';

    public const string HISTORICAL_FUTURE_FINISHED_AT_ERROR = 'Finished time cannot be in the future';

    public function __construct(
        private WorkoutSessionService $sessions,
        private WorkoutSnapshotService $snapshots,
        private WorkoutProgressionService $progressionService,
    ) {}

    /**
     * @throws WorkoutServiceException
     */
    public function createWorkout(Routine $routine, WorkoutMode $mode = WorkoutMode::Standard): Workout
    {
        $routine->load([
            'user',
            ...Routine::SNAPSHOT_STRUCTURE,
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

                $this->snapshots->snapshotRoutineOntoWorkout($workout, $routine, $mode);

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

            $this->snapshots->snapshotRoutineOntoWorkout(
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

            $this->snapshots->applyHistoricalSetLogs($workout, $blocksPayload, $routineBlocksByPosition, $finishedAt);

            $bumps = $workout->isEligibleForProgressionReEval()
                ? $this->progressionService->applyCarryForwardAndCollectBumps($workout)
                : BumpProposalData::collect([], DataCollection::class);

            return [$workout->fresh(['blocks']), $bumps];
        });
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
        return $this->sessions->completeSet($set, $reps, $weightGrams, $segmentWeightGrams, $plateStack);
    }

    /**
     * @param  list<int>  $segmentWeightGrams
     *
     * @throws WorkoutServiceException
     */
    public function promoteToDropset(WorkoutSet $set, array $segmentWeightGrams): WorkoutSet
    {
        return $this->sessions->promoteToDropset($set, $segmentWeightGrams);
    }

    /**
     * @throws WorkoutServiceException
     */
    public function demoteFromDropset(WorkoutSet $set): WorkoutSet
    {
        return $this->sessions->demoteFromDropset($set);
    }

    /**
     * @throws WorkoutServiceException
     */
    public function addWorkingSet(WorkoutBlock $block): WorkoutBlock
    {
        return $this->sessions->addWorkingSet($block);
    }

    /**
     * Append a single-exercise block to the in-progress workout snapshot.
     *
     * @throws WorkoutServiceException
     */
    public function addAdHocExercise(Workout $workout, int $exerciseId): WorkoutBlock
    {
        return $this->sessions->addAdHocExercise($workout, $exerciseId);
    }

    /**
     * Remove an empty ad-hoc block from an in-progress workout snapshot.
     *
     * @throws WorkoutServiceException
     */
    public function removeAdHocBlock(WorkoutBlock $block): void
    {
        $this->sessions->removeAdHocBlock($block);
    }

    /**
     * @throws WorkoutServiceException
     */
    public function removeWorkingSetRound(WorkoutSet $set): void
    {
        $this->sessions->removeWorkingSetRound($set);
    }

    /**
     * Delete every incomplete set in the block (warm-up + working). Logged sets stay.
     * Working / warm-up set_count may become 0.
     *
     * @throws WorkoutServiceException
     */
    public function skipRestOfBlock(WorkoutBlock $block): void
    {
        $this->sessions->skipRestOfBlock($block);
    }

    /**
     * @throws WorkoutServiceException
     */
    public function parkBlockForLater(WorkoutBlock $block): void
    {
        $this->sessions->parkBlockForLater($block);
    }

    /**
     * @throws WorkoutServiceException
     */
    public function clearParkedBlocks(Workout $workout): void
    {
        $this->sessions->clearParkedBlocks($workout);
    }

    /**
     * @return DataCollection<int, BumpProposalData>
     *
     * @throws WorkoutServiceException
     */
    public function finishWorkout(Workout $workout): DataCollection
    {
        return DB::transaction(function () use ($workout): DataCollection {
            $locked = $this->sessions->lockInProgressWorkout($workout);

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
            $locked = $this->sessions->lockInProgressWorkout($workout);

            $locked->status = WorkoutStatus::Discarded;
            $locked->save();

            $workout->status = $locked->status;
        });
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
        $this->snapshots->snapshotRoutineOntoWorkout(
            $workout,
            $routine,
            $mode,
            $routineBlockPositions,
            $workingSetCountByPosition,
        );
    }
}
