<?php

namespace App\Routines\Services;

use App\ExerciseProfiles\Support\ExerciseProfileAssignment;
use App\Routines\Data\Editor\SyncBlockExerciseData;
use App\Routines\Data\Editor\SyncDropsetData;
use App\Routines\Data\Editor\SyncRoutineBlockData;
use App\Routines\Data\Editor\SyncWarmUpData;
use App\Routines\Data\Editor\SyncWarmUpStepData;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Routines\Support\RoutineBlockShape;
use App\Shared\Data\WeightKgSegmentData;
use App\Shared\Enums\BlockType;
use App\Shared\Enums\SetGroupType;
use App\Shared\Enums\WarmUpWeightMode;
use App\Shared\Support\Weight;
use InvalidArgumentException;

final readonly class RoutineBlockWriter
{
    public function __construct(
        private RoutineBlockProfileGuard $profiles,
        private RoutineBlockExerciseWriter $exercises,
    ) {}

    public function create(
        Routine $routine,
        int $position,
        SyncRoutineBlockData $blockData,
        bool $isLastBlock = false,
    ): void {
        $blockType = $blockData->blockType();
        $isSuperset = $blockType === BlockType::Superset;
        $isCircuit = $blockType === BlockType::Circuit;
        $exercises = $blockData->exercises->all();
        $warmUp = $blockData->warmUp ?? new SyncWarmUpData;
        $steps = $warmUp->stepList();
        $dropsets = $blockData->working->dropsetList();

        RoutineBlockShape::assert($blockType, $exercises, $dropsets, $steps, $blockData->sharedProfileId);

        $sharedProfile = $this->profiles->resolveSharedProfile(
            $routine->user,
            $blockData,
            $exercises,
            $isSuperset,
            $isCircuit,
        );
        $this->profiles->assertSharedProfileNotTampered($sharedProfile, $blockData, $steps);
        $sharedFingerprint = ExerciseProfileAssignment::sharedProfileFingerprint(
            $sharedProfile,
            $blockData->sharedProfileFingerprint,
        );

        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'shared_exercise_profile_id' => $sharedProfile?->id,
            'shared_profile_fingerprint' => $sharedFingerprint,
            'position' => $position,
            'type' => $blockType,
            'is_superset' => $isSuperset,
            'stage_rest_seconds' => $isCircuit ? ($blockData->stageRestSeconds ?? 15) : null,
            'has_setup_after' => $isLastBlock ? false : $blockData->hasSetupAfter,
            'has_setup_after_warm_up' => $isCircuit ? false : $blockData->hasSetupAfterWarmUp,
        ]);

        foreach (array_values($exercises) as $index => $exerciseData) {
            /** @var SyncBlockExerciseData $exerciseData */
            $this->exercises->create(
                $routine->user,
                $block,
                $exerciseData,
                $index + 1,
                $isSuperset,
                $isCircuit,
                $sharedProfile,
            );
        }

        $workingGroup = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => $blockData->working->setCount,
            'rest_seconds' => $blockData->working->restSeconds,
        ]);

        $this->persistDropsets($workingGroup, $blockData->working->setCount, $dropsets);

        if (! $isCircuit) {
            $this->persistWarmUpGroup($block, $warmUp, $steps);
        }
    }

    /**
     * @param  list<SyncWarmUpStepData>  $steps
     */
    private function persistWarmUpGroup(RoutineBlock $block, SyncWarmUpData $warmUp, array $steps): void
    {
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
                'weight_mode' => $step->mode,
                'percent_of_working' => $step->mode === WarmUpWeightMode::Percent
                    ? min(100, (int) $step->percent)
                    : null,
                'weight_g' => $step->mode === WarmUpWeightMode::Fixed && $step->weightKg !== null
                    ? Weight::kgToGrams($step->weightKg)
                    : null,
                'reps' => min(100, $step->reps),
                'has_setup_after' => $step->hasSetupAfter,
            ]);
        }
    }

    /**
     * @param  list<SyncDropsetData>  $dropsets
     */
    private function persistDropsets(RoutineSetGroup $workingGroup, int $setCount, array $dropsets): void
    {
        /** @var list<int> $seenIndexes */
        $seenIndexes = [];

        foreach ($dropsets as $dropset) {
            if ($dropset->setIndex < 0 || $dropset->setIndex >= $setCount) {
                throw new InvalidArgumentException(
                    "Dropset set index {$dropset->setIndex} is outside working set count {$setCount}."
                );
            }

            if (in_array($dropset->setIndex, $seenIndexes, true)) {
                throw new InvalidArgumentException(
                    "Duplicate dropset entry for set index {$dropset->setIndex}."
                );
            }

            $seenIndexes[] = $dropset->setIndex;

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
