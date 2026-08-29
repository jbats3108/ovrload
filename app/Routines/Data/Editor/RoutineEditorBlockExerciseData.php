<?php

namespace App\Routines\Data\Editor;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class RoutineEditorBlockExerciseData extends Data
{
    public function __construct(
        public readonly int $exerciseId,
        public readonly float $workingWeightKg,
        public readonly int $prescribedReps,
        public readonly ?int $achievementFloor = null,
        public readonly ?int $progressionTarget = null,
        public readonly ?bool $floorIsDerived = null,
        public readonly ?int $exerciseProfileId = null,
        public readonly ?string $exerciseProfileFingerprint = null,
        public readonly ?int $deloadExerciseId = null,
        public readonly ?float $deloadWorkingWeightKg = null,
    ) {}
}
