<?php

namespace App\Workouts\Data\History;

use App\Routines\Models\RoutineBlockExercise;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class HistoricalCreateWarmUpPrefillData extends Data
{
    public function __construct(
        public readonly int $exercisePosition,
        public readonly string $exerciseName,
        public readonly int $setIndex,
        public readonly int $percentOfWorking,
        public readonly int $reps,
    ) {}

    public static function fromExerciseStep(
        RoutineBlockExercise $exercise,
        int $setIndex,
        int $percentOfWorking,
        int $reps,
    ): self {
        return new self(
            exercisePosition: $exercise->position,
            exerciseName: $exercise->exercise->getName(),
            setIndex: $setIndex,
            percentOfWorking: $percentOfWorking,
            reps: $reps,
        );
    }
}
