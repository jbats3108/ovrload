<?php

namespace App\Workouts\Data\Player;

use App\Exercises\Models\Exercise;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class StoreAdHocExerciseData extends Data
{
    public function __construct(
        #[Exists(Exercise::class, 'id')]
        public readonly int $exerciseId,
    ) {}
}
