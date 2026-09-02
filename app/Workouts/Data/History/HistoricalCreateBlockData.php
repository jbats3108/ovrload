<?php

namespace App\Workouts\Data\History;

use App\Routines\Data\RoutineBlockStructureData;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Shared\Enums\WarmUpWeightMode;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class HistoricalCreateBlockData extends Data
{
    /**
     * @param  DataCollection<int, HistoricalCreateExerciseData>  $exercises
     * @param  DataCollection<int, HistoricalCreateSetPrefillData>  $workingSets
     * @param  DataCollection<int, HistoricalCreateWarmUpPrefillData>  $warmUps
     */
    public function __construct(
        public readonly int $position,
        public readonly bool $isSuperset,
        #[DataCollectionOf(HistoricalCreateExerciseData::class)]
        public readonly DataCollection $exercises,
        public readonly int $workingSetCount,
        #[DataCollectionOf(HistoricalCreateSetPrefillData::class)]
        public readonly DataCollection $workingSets,
        #[DataCollectionOf(HistoricalCreateWarmUpPrefillData::class)]
        public readonly DataCollection $warmUps,
    ) {}

    public static function fromRoutineBlock(RoutineBlock $block): self
    {
        $structure = RoutineBlockStructureData::fromRoutineBlock($block);

        $exercises = $structure->blockExercises
            ->map(fn (RoutineBlockExercise $exercise): HistoricalCreateExerciseData => HistoricalCreateExerciseData::fromRoutineBlockExercise($exercise))
            ->values();

        $workingGroup = $structure->workingGroup;
        $warmUpGroup = $structure->warmUpGroup;

        $setCount = $workingGroup !== null ? $workingGroup->set_count : 1;
        $segmentsByIndex = $workingGroup?->dropsetSegments->groupBy('set_index') ?? collect();

        $workingSets = collect();
        for ($setIndex = 0; $setIndex < $setCount; $setIndex++) {
            $recipeSegments = $segmentsByIndex->get($setIndex, collect())->sortBy('position')->values();

            foreach ($structure->blockExercises as $exercise) {
                $workingSets->push(HistoricalCreateSetPrefillData::fromExercise(
                    $exercise,
                    $setIndex,
                    $recipeSegments,
                ));
            }
        }

        $warmUps = collect();
        if ($warmUpGroup !== null) {
            foreach ($warmUpGroup->warmUpSteps->sortBy('position')->values() as $step) {
                $setIndex = max(0, $step->position - 1);
                foreach ($structure->blockExercises as $exercise) {
                    $warmUps->push(HistoricalCreateWarmUpPrefillData::fromExerciseStep(
                        $exercise,
                        $setIndex,
                        $step->weight_mode ?? WarmUpWeightMode::Percent,
                        $step->percent_of_working !== null ? (int) $step->percent_of_working : null,
                        $step->weight_g !== null ? (int) $step->weight_g : null,
                        (int) $step->reps,
                    ));
                }
            }
        }

        return new self(
            position: $structure->position,
            isSuperset: $structure->isSuperset,
            exercises: HistoricalCreateExerciseData::collect($exercises, DataCollection::class),
            workingSetCount: $setCount,
            workingSets: HistoricalCreateSetPrefillData::collect($workingSets, DataCollection::class),
            warmUps: HistoricalCreateWarmUpPrefillData::collect($warmUps, DataCollection::class),
        );
    }
}
