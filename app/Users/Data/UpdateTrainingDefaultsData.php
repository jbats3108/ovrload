<?php

namespace App\Users\Data;

use App\Routines\Data\Editor\SyncWarmUpStepData;
use App\Shared\Data\Validation\DeloadEveryN;
use App\Shared\Data\Validation\DeloadRepsFactor;
use App\Shared\Data\Validation\DeloadWeightFactor;
use App\Users\Enums\ProgressionStyle;
use App\Users\Enums\ProgressiveMidBlock;
use App\Users\Enums\WarmUpDefaultsScope;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class UpdateTrainingDefaultsData extends Data
{
    /**
     * @param  DataCollection<int, SyncWarmUpStepData>|null  $warmUpStepsDefault
     */
    public function __construct(
        /** null means empty list from the form. */
        #[DataCollectionOf(SyncWarmUpStepData::class)]
        public readonly ?DataCollection $warmUpStepsDefault = null,

        #[Enum(WarmUpDefaultsScope::class)]
        public readonly WarmUpDefaultsScope $warmUpDefaultsScope = WarmUpDefaultsScope::AllBlocks,

        #[Nullable, Min(1), Max(100)]
        public readonly ?int $achievementFloorDefault = null,

        #[Min(1), Max(100)]
        public readonly int $progressionTargetDefault = 6,

        #[Enum(ProgressionStyle::class)]
        public readonly ProgressionStyle $progressionStyleDefault = ProgressionStyle::StraightSets,

        #[Enum(ProgressiveMidBlock::class)]
        public readonly ProgressiveMidBlock $progressiveMidBlockDefault = ProgressiveMidBlock::Ask,

        #[DeloadWeightFactor]
        public readonly float $deloadWeightFactorDefault = 0.5,

        #[DeloadRepsFactor]
        public readonly float $deloadRepsFactorDefault = 2.0,

        #[DeloadEveryN]
        public readonly int $deloadEveryNDefault = 3,
    ) {}
}
