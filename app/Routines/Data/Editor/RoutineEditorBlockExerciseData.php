<?php

namespace App\Routines\Data\Editor;

use App\Shared\Enums\PrescriptionMode;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class RoutineEditorBlockExerciseData extends Data
{
    public function __construct(
        public readonly int $exerciseId,
        public readonly float $workingWeightKg,
        public readonly ?int $prescribedReps = null,
        public readonly PrescriptionMode $prescriptionMode = PrescriptionMode::Reps,
        public readonly ?int $prescribedDurationSeconds = null,
        public readonly ?int $achievementFloor = null,
        public readonly ?int $progressionTarget = null,
        public readonly ?bool $floorIsDerived = null,
        public readonly ?int $exerciseProfileId = null,
        public readonly ?string $exerciseProfileFingerprint = null,
        public readonly ?int $deloadExerciseId = null,
        public readonly ?float $deloadWorkingWeightKg = null,
    ) {}
}
