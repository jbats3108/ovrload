<?php

namespace App\ExerciseProfiles\Data;

use App\Shared\Enums\WarmUpWeightMode;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class ExerciseProfileWarmUpStepData extends Data
{
    public function __construct(
        public readonly WarmUpWeightMode $mode = WarmUpWeightMode::Percent,

        #[Min(1), Max(100)]
        public readonly ?int $percent = null,

        #[Min(1), Max(100)]
        public readonly int $reps = 5,
    ) {}
}
