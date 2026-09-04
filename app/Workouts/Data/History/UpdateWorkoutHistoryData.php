<?php

namespace App\Workouts\Data\History;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class UpdateWorkoutHistoryData extends Data
{
    /**
     * @param  DataCollection<int, UpdateHistoryWorkingSetData>  $sets
     */
    public function __construct(
        #[DataCollectionOf(UpdateHistoryWorkingSetData::class)]
        #[Min(1)]
        public readonly DataCollection $sets,
    ) {}
}
