<?php

namespace App\Workouts\Data\History;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class StoreHistoricalBlockData extends Data
{
    /**
     * @param  DataCollection<int, StoreHistoricalSetData>  $sets
     * @param  DataCollection<int, StoreHistoricalSetData>|null  $warmUpSets
     */
    public function __construct(
        #[Min(1)]
        public readonly int $position,

        #[Min(1)]
        public readonly int $workingSetCount,

        #[DataCollectionOf(StoreHistoricalSetData::class)]
        #[Min(1)]
        public readonly DataCollection $sets,

        #[Nullable]
        #[DataCollectionOf(StoreHistoricalSetData::class)]
        public readonly ?DataCollection $warmUpSets = null,
    ) {}
}
