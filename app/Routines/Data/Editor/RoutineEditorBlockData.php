<?php

namespace App\Routines\Data\Editor;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class RoutineEditorBlockData extends Data
{
    /**
     * @param  DataCollection<int, RoutineEditorBlockExerciseData>  $exercises
     */
    public function __construct(
        public readonly bool $isSuperset,
        public readonly bool $hasSetupAfter,
        public readonly bool $hasSetupAfterWarmUp,
        public readonly ?int $sharedProfileId,
        public readonly ?string $sharedProfileFingerprint,
        #[DataCollectionOf(RoutineEditorBlockExerciseData::class)]
        public readonly DataCollection $exercises,
        public readonly SyncSetGroupData $working,
        public readonly SyncWarmUpData $warmUp,
    ) {}
}
