<?php

namespace App\ExerciseProfiles\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class ExerciseProfileWarmUpStepData extends Data
{
    public function __construct(
        #[Min(1), Max(100)]
        public readonly int $percent,

        #[Min(1), Max(100)]
        public readonly int $reps,
    ) {}
}
