<?php

namespace App\Routines\Data\Editor;

use App\Shared\Enums\WarmUpWeightMode;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class SyncWarmUpData extends Data
{
    /**
     * @param  DataCollection<int, SyncWarmUpStepData>|null  $steps
     */
    public function __construct(
        #[Min(0), Max(20)]
        public readonly int $setCount = 0,

        #[Min(0), Max(3600)]
        public readonly int $restSeconds = 60,

        /** Empty collection is valid (no warm-up steps). */
        #[DataCollectionOf(SyncWarmUpStepData::class)]
        public readonly ?DataCollection $steps = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    #[Override]
    public static function prepareForPipeline(array $properties): array
    {
        return BlankRestSeconds::inGroup($properties);
    }

    /**
     * @return list<SyncWarmUpStepData>
     */
    public function stepList(): array
    {
        if ($this->steps === null) {
            return [];
        }

        return array_values(array_filter(
            $this->steps->all(),
            static fn (SyncWarmUpStepData $step): bool => $step->reps > 0
                && ($step->mode === WarmUpWeightMode::Bar || ($step->percent ?? 0) > 0)
        ));
    }
}
