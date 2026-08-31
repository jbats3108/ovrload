<?php

namespace App\Workouts\Data\Player;

use App\Exercises\Enums\ExerciseEquipment;
use App\Shared\Enums\SetGroupType;
use App\Shared\Enums\WarmUpWeightMode;
use App\Shared\Support\WarmUpStepSupport;
use App\Shared\Support\Weight;
use App\Workouts\Data\PlateStackData;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Models\WorkoutWarmUpStep;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WorkoutPlayerSetData extends Data
{
    /**
     * @param  DataCollection<int, WorkoutPlayerSetSegmentData>  $segments
     */
    public function __construct(
        public readonly int $id,
        public readonly int $workoutBlockExerciseId,
        public readonly string $exerciseName,
        public readonly ?string $equipment,
        public readonly int $setIndex,
        public readonly string $groupType,
        public readonly ?float $targetWeightKg,
        public readonly ?int $targetReps,
        public readonly ?float $loggedWeightKg,
        public readonly ?PlateStackData $plateStack,
        public readonly ?int $loggedReps,
        public readonly bool $completed,
        public readonly int $restSeconds,
        public readonly bool $hasSetupAfter,
        public readonly bool $isDropset,
        #[DataCollectionOf(WorkoutPlayerSetSegmentData::class)]
        public readonly DataCollection $segments,
    ) {}

    public static function fromSet(
        WorkoutSet $set,
        string $exerciseName,
        ?ExerciseEquipment $equipment,
        int $workingWeightG,
        int $prescribedReps,
        SetGroupType $groupType,
        int $restSeconds,
        ?WorkoutWarmUpStep $warmUpStep = null,
        ?int $defaultBarWeightG = null,
    ): self {
        $set->loadMissing('segments');

        $isDropset = $set->isDropset();
        $segments = $set->segments
            ->sortBy('position')
            ->values()
            ->map(fn ($segment): WorkoutPlayerSetSegmentData => new WorkoutPlayerSetSegmentData(
                position: $segment->position,
                weightKg: Weight::gramsToKg($segment->weight_g),
            ));

        $targetWeightG = null;
        if (! $isDropset) {
            if ($groupType === SetGroupType::WarmUp && $warmUpStep !== null) {
                $mode = $warmUpStep->weight_mode ?? WarmUpWeightMode::Percent;
                $targetWeightG = WarmUpStepSupport::targetWeightG(
                    $mode,
                    $warmUpStep->percent_of_working,
                    $workingWeightG,
                    $defaultBarWeightG,
                    $equipment,
                    $warmUpStep->weight_g,
                );
            } else {
                $targetWeightG = $workingWeightG;
            }
        }

        return new self(
            id: $set->id,
            workoutBlockExerciseId: $set->workout_block_exercise_id,
            exerciseName: $exerciseName,
            equipment: $equipment?->value,
            setIndex: $set->set_index,
            groupType: $groupType->value,
            targetWeightKg: $targetWeightG !== null ? Weight::gramsToKg($targetWeightG) : null,
            targetReps: $groupType === SetGroupType::WarmUp
                ? ($warmUpStep !== null ? $warmUpStep->reps : null)
                : $prescribedReps,
            loggedWeightKg: $set->weight_g !== null ? Weight::gramsToKg($set->weight_g) : null,
            plateStack: PlateStackData::fromSnapshot($set->plate_stack),
            loggedReps: $set->reps,
            completed: $set->completed_at !== null,
            restSeconds: $restSeconds,
            hasSetupAfter: $groupType === SetGroupType::WarmUp
                && $warmUpStep !== null
                && (bool) $warmUpStep->has_setup_after,
            isDropset: $isDropset,
            segments: WorkoutPlayerSetSegmentData::collect($segments, DataCollection::class),
        );
    }
}
