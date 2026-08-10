<?php

namespace App\Workouts\Data\History;

use App\Routines\Models\RoutineBlockExercise;
use App\Shared\Support\Weight;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class HistoricalCreateExerciseData extends Data
{
    public function __construct(
        public readonly int $position,
        public readonly string $name,
        public readonly ?string $equipment,
        public readonly float $workingWeightKg,
        public readonly int $prescribedReps,
    ) {}

    public static function fromRoutineBlockExercise(RoutineBlockExercise $exercise): self
    {
        return new self(
            position: $exercise->position,
            name: $exercise->exercise->getName(),
            equipment: $exercise->exercise->equipment?->value,
            workingWeightKg: Weight::gramsToKg($exercise->working_weight_g),
            prescribedReps: $exercise->prescribed_reps,
        );
    }
}
