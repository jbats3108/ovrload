<?php

namespace App\Routines\Data\Editor;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class RoutineEditorExerciseOptionData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $primaryMuscleGroup,
        public readonly bool $isCustom = false,
    ) {}
}
