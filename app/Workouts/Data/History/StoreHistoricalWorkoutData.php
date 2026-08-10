<?php

namespace App\Workouts\Data\History;

use App\Workouts\Enums\WorkoutMode;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class StoreHistoricalWorkoutData extends Data
{
    /**
     * @param  DataCollection<int, StoreHistoricalBlockData>  $blocks
     */
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, type: Carbon::class, format: [
            'Y-m-d\\TH:i:sP',
            'Y-m-d\\TH:i:s',
            'Y-m-d\\TH:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ])]
        #[BeforeOrEqual('now')]
        public readonly Carbon $finishedAt,

        #[DataCollectionOf(StoreHistoricalBlockData::class)]
        #[Min(1)]
        public readonly DataCollection $blocks,

        #[Enum(WorkoutMode::class)]
        public readonly ?WorkoutMode $mode = null,
    ) {}

    public function modeOrDefault(): WorkoutMode
    {
        return $this->mode ?? WorkoutMode::Standard;
    }
}
