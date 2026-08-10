<?php

namespace App\Exercises\Data;

use App\Exercises\Models\Exercise;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class CustomExerciseCreatedData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $primaryMuscleGroup,
        public readonly bool $isCustom = true,
    ) {}

    public static function fromExercise(Exercise $exercise): self
    {
        $exercise->loadMissing('primaryMuscleGroup');

        return new self(
            id: $exercise->id,
            name: $exercise->getName(),
            primaryMuscleGroup: $exercise->primaryMuscleGroup->getName(),
            isCustom: true,
        );
    }
}
