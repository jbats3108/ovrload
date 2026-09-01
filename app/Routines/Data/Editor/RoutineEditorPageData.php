<?php

namespace App\Routines\Data\Editor;

use App\Routines\Data\RoutineBlockStructureData;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Shared\Enums\WarmUpWeightMode;
use App\Shared\Support\Weight;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class RoutineEditorPageData extends Data
{
    /**
     * @param  DataCollection<int, RoutineEditorBlockData>  $blocks
     * @param  DataCollection<int, RoutineEditorExerciseOptionData>  $exercises
     */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?int $defaultExerciseProfileId,
        public readonly float $deloadWeightFactor,
        public readonly float $deloadRepsFactor,
        public readonly int $deloadEveryN,
        public readonly string $updatedAt,
        #[DataCollectionOf(RoutineEditorBlockData::class)]
        public readonly DataCollection $blocks,
        #[DataCollectionOf(RoutineEditorExerciseOptionData::class)]
        public readonly DataCollection $exercises,
        public readonly string $weightUnit,
    ) {}

    /**
     * @param  DataCollection<int, RoutineEditorExerciseOptionData>  $exercises
     */
    public static function fromRoutine(Routine $routine, DataCollection $exercises, string $weightUnit): self
    {
        $routine->loadMissing(Routine::EDITOR_STRUCTURE);

        $blocks = $routine->blocks->map(function (RoutineBlock $block): RoutineEditorBlockData {
            $structure = RoutineBlockStructureData::fromRoutineBlock($block);
            $working = $structure->workingGroup;
            $warmUp = $structure->warmUpGroup;

            return new RoutineEditorBlockData(
                isSuperset: $structure->isSuperset,
                hasSetupAfter: $structure->hasSetupAfter,
                hasSetupAfterWarmUp: $structure->hasSetupAfterWarmUp,
                sharedProfileId: $structure->sharedProfileId,
                sharedProfileFingerprint: $structure->sharedProfileFingerprint,
                exercises: RoutineEditorBlockExerciseData::collect(
                    $structure->blockExercises->map(fn (RoutineBlockExercise $row): RoutineEditorBlockExerciseData => new RoutineEditorBlockExerciseData(
                        exerciseId: $row->exercise_id,
                        workingWeightKg: Weight::gramsToKg($row->working_weight_g),
                        prescribedReps: $row->prescribed_reps,
                        achievementFloor: $row->achievement_floor_override,
                        progressionTarget: $row->progression_target_override,
                        floorIsDerived: $row->floor_is_derived,
                        exerciseProfileId: $row->exercise_profile_id,
                        exerciseProfileFingerprint: $row->exercise_profile_fingerprint,
                        deloadExerciseId: $row->deload_exercise_id,
                        deloadWorkingWeightKg: $row->deload_working_weight_g !== null
                            ? Weight::gramsToKg($row->deload_working_weight_g)
                            : null,
                    )),
                    DataCollection::class,
                ),
                working: new SyncSetGroupData(
                    setCount: $working !== null ? $working->set_count : 3,
                    restSeconds: $working !== null ? $working->rest_seconds : 120,
                    dropsets: $structure->dropsets,
                ),
                warmUp: new SyncWarmUpData(
                    setCount: $warmUp !== null ? $warmUp->set_count : 0,
                    restSeconds: $warmUp !== null ? $warmUp->rest_seconds : 60,
                    steps: SyncWarmUpStepData::collect(
                        $warmUp !== null
                            ? $warmUp->warmUpSteps->map(fn ($step): SyncWarmUpStepData => new SyncWarmUpStepData(
                                mode: $step->weight_mode ?? WarmUpWeightMode::Percent,
                                percent: $step->percent_of_working !== null ? (int) $step->percent_of_working : null,
                                weightKg: $step->weight_g !== null ? Weight::gramsToKg((int) $step->weight_g) : null,
                                reps: (int) ($step->reps ?? 5),
                                hasSetupAfter: (bool) $step->has_setup_after,
                            ))
                            : [],
                        DataCollection::class,
                    ),
                ),
            );
        });

        return new self(
            id: $routine->id,
            slug: $routine->getSlug(),
            name: $routine->getName(),
            defaultExerciseProfileId: $routine->default_exercise_profile_id,
            deloadWeightFactor: (float) $routine->deload_weight_factor,
            deloadRepsFactor: (float) $routine->deload_reps_factor,
            deloadEveryN: (int) $routine->deload_every_n,
            updatedAt: $routine->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            blocks: RoutineEditorBlockData::collect($blocks, DataCollection::class),
            exercises: $exercises,
            weightUnit: $weightUnit,
        );
    }
}
