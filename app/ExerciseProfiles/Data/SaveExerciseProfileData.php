<?php

namespace App\ExerciseProfiles\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class SaveExerciseProfileData extends Data
{
    /**
     * @param  DataCollection<int, ExerciseProfileWarmUpStepData>|null  $warmUpSteps
     */
    public function __construct(
        #[Max(255)]
        public readonly string $name,

        #[Min(1), Max(100)]
        public readonly int $targetReps,

        #[Nullable, Min(1), Max(100)]
        public readonly ?int $floorOverride = null,

        #[Min(0), Max(3600)]
        public readonly int $workingRestSeconds = 120,

        #[DataCollectionOf(ExerciseProfileWarmUpStepData::class)]
        public readonly ?DataCollection $warmUpSteps = null,
    ) {}
}
