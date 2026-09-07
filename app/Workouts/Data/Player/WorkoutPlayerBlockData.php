<?php

namespace App\Workouts\Data\Player;

use App\Shared\Enums\BlockType;
use App\Shared\Enums\PrescriptionMode;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Models\WorkoutBlockExercise;
use App\Workouts\Models\WorkoutSet;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WorkoutPlayerBlockData extends Data
{
    /**
     * @param  DataCollection<int, WorkoutPlayerExerciseData>  $exercises
     * @param  DataCollection<int, WorkoutPlayerSetData>  $sets
     */
    public function __construct(
        public readonly int $id,
        public readonly int $position,
        public readonly bool $isSuperset,
        public readonly bool $isAdHoc,
        public readonly bool $isParked,
        public readonly bool $hasSetupAfter,
        public readonly bool $hasSetupAfterWarmUp,
        #[DataCollectionOf(WorkoutPlayerExerciseData::class)]
        public readonly DataCollection $exercises,
        #[DataCollectionOf(WorkoutPlayerSetData::class)]
        public readonly DataCollection $sets,
        public readonly string $type = 'single',
        public readonly ?int $stageRestSeconds = null,
    ) {}

    public static function fromBlock(WorkoutBlock $block, ?int $defaultBarWeightG = null): self
    {
        $block->loadMissing(['blockExercises', 'setGroups.sets.segments', 'setGroups.warmUpSteps']);

        $sortedExercises = $block->blockExercises->sortBy('position')->values();
        $lastExerciseId = $sortedExercises->last()?->id;
        $exercisesById = $sortedExercises->keyBy('id');

        $setRows = $block->setGroups
            ->sortBy(fn ($group): int => $group->type === SetGroupType::WarmUp ? 0 : 1)
            ->flatMap(function ($group) use ($block, $exercisesById, $lastExerciseId, $defaultBarWeightG) {
                $warmUpSteps = $group->warmUpSteps->keyBy('position');

                return $group->sets
                    ->sortBy(function (WorkoutSet $set) use ($exercisesById): string {
                        $exercise = $exercisesById->get($set->workout_block_exercise_id);

                        return sprintf(
                            '%04d-%04d',
                            $set->set_index,
                            $exercise !== null ? $exercise->position : 0,
                        );
                    })
                    ->map(function (WorkoutSet $set) use ($block, $group, $exercisesById, $lastExerciseId, $warmUpSteps, $defaultBarWeightG): WorkoutPlayerSetData {
                        /** @var WorkoutBlockExercise $exercise */
                        $exercise = $exercisesById->get($set->workout_block_exercise_id);

                        $warmUpStep = $group->type === SetGroupType::WarmUp
                            ? $warmUpSteps->get($set->set_index + 1)
                            : null;

                        $isLastExerciseInCircuit = $block->isCircuit() && $exercise->id === $lastExerciseId;
                        $restSeconds = $block->isCircuit()
                            ? ($isLastExerciseInCircuit ? ($group->rest_seconds ?? 60) : ($block->stage_rest_seconds ?? 15))
                            : ($group->rest_seconds ?? 0);

                        return WorkoutPlayerSetData::fromSet(
                            $set,
                            $exercise->exercise_name,
                            $exercise->equipment,
                            $exercise->working_weight_g,
                            $exercise->prescribed_reps,
                            $group->type,
                            $restSeconds,
                            $warmUpStep,
                            $defaultBarWeightG,
                            $exercise->prescription_mode ?? PrescriptionMode::Reps,
                            $exercise->prescribed_duration_seconds,
                        );
                    });
            })
            ->values();

        return new self(
            id: $block->id,
            position: $block->position,
            isSuperset: $block->is_superset,
            isAdHoc: $block->is_ad_hoc,
            isParked: $block->is_parked,
            hasSetupAfter: $block->has_setup_after,
            hasSetupAfterWarmUp: $block->has_setup_after_warm_up,
            exercises: WorkoutPlayerExerciseData::collect(
                $sortedExercises->map(fn (WorkoutBlockExercise $exercise): WorkoutPlayerExerciseData => WorkoutPlayerExerciseData::fromBlockExercise($exercise)),
                DataCollection::class,
            ),
            sets: WorkoutPlayerSetData::collect($setRows, DataCollection::class),
            type: ($block->type ?? ($block->is_superset ? BlockType::Superset : BlockType::Single))->value,
            stageRestSeconds: $block->stage_rest_seconds,
        );
    }
}
