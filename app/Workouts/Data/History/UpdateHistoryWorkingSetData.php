<?php

namespace App\Workouts\Data\History;

use App\Shared\Data\WeightKgSegmentData;
use App\Shared\Support\Weight;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class UpdateHistoryWorkingSetData extends Data
{
    /**
     * @param  DataCollection<int, WeightKgSegmentData>|null  $segments
     */
    public function __construct(
        #[Min(1)]
        public readonly int $id,

        #[Min(0), Max(100)]
        public readonly int $reps,

        #[Nullable, Min(0)]
        #[RequiredWithout('segments')]
        public readonly ?float $weightKg = null,

        #[Nullable]
        #[DataCollectionOf(WeightKgSegmentData::class)]
        #[Min(2), Max(20)]
        public readonly ?DataCollection $segments = null,
    ) {}

    public function weightGrams(): ?int
    {
        if ($this->weightKg === null) {
            return null;
        }

        return Weight::kgToGrams($this->weightKg);
    }

    /**
     * @return list<int>|null
     */
    public function segmentWeightGrams(): ?array
    {
        if ($this->segments === null) {
            return null;
        }

        return WeightKgSegmentData::gramsList($this->segments);
    }
}
