<?php

namespace App\Workouts\Data\History;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class HistoricalCreateSegmentPrefillData extends Data
{
    public function __construct(
        public readonly float $weightKg,
    ) {}
}
