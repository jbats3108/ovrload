<?php

namespace App\Workouts\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class InProgressWorkoutData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $routineName,
        public readonly string $mode,
        public readonly int $parkedIncompleteCount = 0,
    ) {}
}
