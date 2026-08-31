<?php

namespace App\Routines\Data\Editor;

use App\Shared\Enums\WarmUpWeightMode;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class SyncWarmUpStepData extends Data
{
    public function __construct(
        public readonly WarmUpWeightMode $mode = WarmUpWeightMode::Percent,

        #[Nullable, Min(1), Max(100)]
        public readonly ?int $percent = null,

        #[Min(1), Max(100)]
        public readonly int $reps = 5,

        public readonly bool $hasSetupAfter = false,
    ) {}
}
