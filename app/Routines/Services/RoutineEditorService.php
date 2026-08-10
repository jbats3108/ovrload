<?php

namespace App\Routines\Services;

use App\Exercises\Models\Exercise;
use App\Routines\Data\Editor\SyncBlockExerciseData;
use App\Routines\Data\Editor\SyncDropsetData;
use App\Routines\Data\Editor\SyncRoutineBlockData;
use App\Routines\Data\Editor\SyncRoutineData;
use App\Routines\Data\Editor\SyncWarmUpData;
use App\Routines\Exceptions\RoutineStaleException;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Data\WeightKgSegmentData;
use App\Shared\Enums\SetGroupType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoutineEditorService
{
    public function sync(Routine $routine, SyncRoutineData $data): Routine
    {
        return DB::transaction(function () use ($routine, $data): Routine {
            $locked = Routine::query()->whereKey($routine->id)->lockForUpdate()->firstOrFail();

            if ($data->expectedUpdatedAt !== null) {
                $expected = Carbon::parse($data->expectedUpdatedAt);
                if ($locked->updated_at === null || $locked->updated_at->getTimestamp() !== $expected->getTimestamp()) {
                    throw new RoutineStaleException;
                }
            }

            $locked->update([
                'name' => $data->name,
                'deload_weight_factor' => $data->deloadWeightFactor ?? $locked->deload_weight_factor,
                'deload_reps_factor' => $data->deloadRepsFactor ?? $locked->deload_reps_factor,
                'deload_every_n' => $data->deloadEveryN ?? $locked->deload_every_n,
            ]);

            $locked->blocks()->each(function (RoutineBlock $block): void {
                $block->delete();
            });

            $blocks = array_values(iterator_to_array($data->blocks ?? []));
            $lastIndex = count($blocks) - 1;

            foreach ($blocks as $index => $blockData) {
                /** @var SyncRoutineBlockData $blockData */
                $this->createBlock($locked, $index + 1, $blockData, $index === $lastIndex);
            }

            return $locked->fresh([
                'blocks.blockExercises.exercise',
                'blocks.setGroups.warmUpSteps',
                'blocks.setGroups.dropsetSegments',
            ]) ?? $locked;
        });
    }

    private function createBlock(Routine $routine, int $position, SyncRoutineBlockData $blockData, bool $isLastBlock = false): void
    {
        $exercises = $blockData->exercises->all();

        if ($blockData->isSuperset && count($exercises) !== 2) {
            throw new InvalidArgumentException('A superset must have exactly two exercises.');
        }

        if (! $blockData->isSuperset && count($exercises) !== 1) {
            throw new InvalidArgumentException('A non-superset must have exactly one exercise.');
        }

        $dropsets = $blockData->working->dropsetList();

        if ($blockData->isSuperset && $dropsets !== []) {
            throw new InvalidArgumentException('Dropsets are not supported on supersets.');
        }

        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => $position,
            'is_superset' => $blockData->isSuperset,
            'has_setup_after' => $isLastBlock ? false : $blockData->hasSetupAfter,
            'has_setup_after_warm_up' => $blockData->hasSetupAfterWarmUp,
        ]);

        foreach (array_values($exercises) as $index => $exerciseData) {
            /** @var SyncBlockExerciseData $exerciseData */
            Exercise::assertAvailableFor($routine->user, $exerciseData->exerciseId);

            if ($exerciseData->deloadExerciseId !== null) {
                Exercise::assertAvailableFor($routine->user, $exerciseData->deloadExerciseId);
            }

            RoutineBlockExercise::create([
                'routine_block_id' => $block->id,
                'exercise_id' => $exerciseData->exerciseId,
                'position' => $index + 1,
                'working_weight_g' => $exerciseData->workingWeightGrams(),
                'deload_exercise_id' => $exerciseData->deloadExerciseId,
                'deload_working_weight_g' => $exerciseData->deloadWorkingWeightGrams(),
                'prescribed_reps' => $exerciseData->prescribedReps,
                'achievement_floor_override' => $exerciseData->achievementFloor,
                'progression_target_override' => $exerciseData->progressionTarget,
            ]);
        }

        $workingGroup = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => $blockData->working->setCount,
            'rest_seconds' => $blockData->working->restSeconds,
        ]);

        $this->persistDropsets($workingGroup, $blockData->working->setCount, $dropsets);

        $warmUp = $blockData->warmUp ?? new SyncWarmUpData;
        $steps = $warmUp->stepList();

        $warmUpGroup = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => max(count($steps), $warmUp->setCount),
            'rest_seconds' => $warmUp->restSeconds,
        ]);

        foreach ($steps as $stepIndex => $step) {
            RoutineWarmUpStep::create([
                'routine_set_group_id' => $warmUpGroup->id,
                'position' => $stepIndex + 1,
                'percent_of_working' => min(100, max(1, $step->percent)),
                'reps' => min(100, max(1, $step->reps)),
                'has_setup_after' => $step->hasSetupAfter,
            ]);
        }
    }

    /**
     * @param  list<SyncDropsetData>  $dropsets
     */
    private function persistDropsets(RoutineSetGroup $workingGroup, int $setCount, array $dropsets): void
    {
        $seenIndexes = [];

        foreach ($dropsets as $dropset) {
            if ($dropset->setIndex < 0 || $dropset->setIndex >= $setCount) {
                throw new InvalidArgumentException(
                    "Dropset set index {$dropset->setIndex} is outside working set count {$setCount}."
                );
            }

            if (isset($seenIndexes[$dropset->setIndex])) {
                throw new InvalidArgumentException(
                    "Duplicate dropset recipe for set index {$dropset->setIndex}."
                );
            }

            $seenIndexes[$dropset->setIndex] = true;

            $segments = array_values($dropset->segments->all());

            if (count($segments) < 2) {
                throw new InvalidArgumentException('A dropset requires at least two segments.');
            }

            foreach ($segments as $segmentIndex => $segment) {
                /** @var WeightKgSegmentData $segment */
                RoutineDropsetSegment::create([
                    'routine_set_group_id' => $workingGroup->id,
                    'set_index' => $dropset->setIndex,
                    'position' => $segmentIndex + 1,
                    'weight_g' => $segment->weightGrams(),
                ]);
            }
        }
    }
}
