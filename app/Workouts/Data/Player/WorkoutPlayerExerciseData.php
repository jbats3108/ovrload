<?php

namespace App\Workouts\Data\Player;

use App\Shared\Enums\PrescriptionMode;
use App\Shared\Support\Weight;
use App\Workouts\Models\WorkoutBlockExercise;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WorkoutPlayerExerciseData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $equipment,
        public readonly float $workingWeightKg,
        public readonly ?int $prescribedReps,
        public readonly ?int $achievementFloor,
        public readonly ?int $progressionTarget,
        public readonly int $position,
        public readonly string $prescriptionMode = 'reps',
        public readonly ?int $prescribedDurationSeconds = null,
    ) {}

    public static function fromBlockExercise(WorkoutBlockExercise $exercise): self
    {
        return new self(
            id: $exercise->id,
            name: $exercise->exercise_name,
            equipment: $exercise->equipment?->value,
            workingWeightKg: Weight::gramsToKg($exercise->working_weight_g),
            prescribedReps: $exercise->prescribed_reps,
            achievementFloor: $exercise->achievement_floor,
            progressionTarget: $exercise->progression_target,
            position: $exercise->position,
            prescriptionMode: ($exercise->prescription_mode ?? PrescriptionMode::Reps)->value,
            prescribedDurationSeconds: $exercise->prescribed_duration_seconds,
        );
    }
}
