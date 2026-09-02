<?php

namespace App\Workouts\Services;

use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Data\History\StoreHistoricalBlockData;
use App\Workouts\Data\History\StoreHistoricalSetData;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Models\WorkoutBlockExercise;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Models\WorkoutSetGroup;
use App\Workouts\Models\WorkoutSetSegment;
use App\Workouts\Models\WorkoutWarmUpStep;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final readonly class WorkoutSnapshotService
{
    public function __construct(
        private WorkoutSetLogger $setLogger,
    ) {}

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

    /**
     * @param  list<StoreHistoricalBlockData>  $blocksPayload
     * @param  Collection<int, RoutineBlock>  $routineBlocksByPosition
     *
     * @throws WorkoutServiceException
     */
    public function applyHistoricalSetLogs(
        Workout $workout,
        array $blocksPayload,
        $routineBlocksByPosition,
        CarbonInterface $finishedAt,
    ): void {
        $setIds = $workout->blocks
            ->flatMap(fn (WorkoutBlock $block) => $block->setGroups->flatMap(
                fn (WorkoutSetGroup $group) => $group->sets->pluck('id'),
            ))
            ->all();

        if ($setIds !== []) {
            WorkoutSetSegment::query()->whereIn('workout_set_id', $setIds)->delete();
        }

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
                throw new WorkoutServiceException(WorkoutService::HISTORICAL_SET_MISMATCH_ERROR);
            }

            $exercisesById = $workoutBlock->blockExercises->keyBy('id');
            $workingGroup = $workoutBlock->setGroups->first(
                fn (WorkoutSetGroup $group): bool => $group->type === SetGroupType::Working
            );

            if ($workingGroup === null) {
                throw new WorkoutServiceException(WorkoutService::WORKING_SET_GROUP_MISSING_ERROR);
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
                    throw new WorkoutServiceException(WorkoutService::HISTORICAL_SET_MISMATCH_ERROR);
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

                return sprintf(
                    '%d:%d',
                    $exercise !== null ? $exercise->position : 0,
                    $set->set_index,
                );
            }
        );

        $seen = [];

        foreach ($sets as $setData) {
            $key = sprintf('%d:%d', $setData->exercisePosition, $setData->setIndex);

            if (isset($seen[$key]) || ! $setsByKey->has($key)) {
                throw new WorkoutServiceException(WorkoutService::HISTORICAL_SET_MISMATCH_ERROR);
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
            throw new WorkoutServiceException(WorkoutService::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        if ($hasSegments) {
            if (count($segmentWeightGrams) < 2) {
                throw new WorkoutServiceException(WorkoutService::DROPSET_REQUIRES_SEGMENTS_ERROR);
            }

            $this->setLogger->applyLoggedValues(
                $set,
                $data->reps,
                segmentWeightGrams: $segmentWeightGrams,
                completedAt: $finishedAt,
                deleteExistingSegments: false,
            );
            $set->save();

            return;
        }

        $weightGrams = $data->weightGrams();

        if ($weightGrams === null) {
            throw new WorkoutServiceException(WorkoutService::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        $this->setLogger->applyLoggedValues(
            $set,
            $data->reps,
            weightGrams: $weightGrams,
            completedAt: $finishedAt,
            deleteExistingSegments: false,
        );
        $set->save();
    }
}
