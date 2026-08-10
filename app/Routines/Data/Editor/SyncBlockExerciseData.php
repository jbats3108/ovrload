<?php

namespace App\Routines\Data\Editor;

use App\Exercises\Models\Exercise;
use App\Shared\Support\Weight;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Different;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\RequiredWith;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class SyncBlockExerciseData extends Data
{
    public function __construct(
        #[Exists(Exercise::class, 'id')]
        public readonly int $exerciseId,

        #[Min(0)]
        public readonly float $workingWeightKg,

        #[Min(1), Max(100)]
        public readonly int $prescribedReps,

        #[Nullable, Min(1), Max(100)]
        public readonly ?int $achievementFloor = null,

        #[Nullable, Min(1), Max(100)]
        public readonly ?int $progressionTarget = null,

        #[Nullable, Exists(Exercise::class, 'id'), Different('exercise_id'), RequiredWith('deload_working_weight_kg')]
        public readonly ?int $deloadExerciseId = null,

        #[Nullable, Min(0), RequiredWith('deload_exercise_id')]
        public readonly ?float $deloadWorkingWeightKg = null,
    ) {}

    public function workingWeightGrams(): int
    {
        return Weight::kgToGrams($this->workingWeightKg);
    }

    public function deloadWorkingWeightGrams(): ?int
    {
        if ($this->deloadWorkingWeightKg === null) {
            return null;
        }

        return Weight::kgToGrams($this->deloadWorkingWeightKg);
    }
}
