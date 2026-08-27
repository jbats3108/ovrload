<?php

namespace App\Workouts\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

final class StartRestTimerData extends Data
{
    public function __construct(
        #[Min(1), Max(3600)]
        public readonly int $seconds,
    ) {}
}
