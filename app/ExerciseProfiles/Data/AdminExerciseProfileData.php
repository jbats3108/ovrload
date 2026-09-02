<?php

namespace App\ExerciseProfiles\Data;

use App\ExerciseProfiles\Models\ExerciseProfile;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class AdminExerciseProfileData extends Data
{
    /**
     * @param  list<array{mode: string, percent?: int, weight_kg?: float, reps: int}>  $warmUpSteps
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $slug,
        public readonly string $status,
        public readonly int $targetReps,
        public readonly int $floor,
        public readonly ?int $floorOverride,
        public readonly int $workingRestSeconds,
        public readonly array $warmUpSteps,
        public readonly string $displayName,
    ) {}

    public static function fromProfile(ExerciseProfile $profile): self
    {
        return new self(
            id: $profile->id,
            name: $profile->name,
            slug: $profile->slug,
            status: $profile->status->value,
            targetReps: $profile->target_reps,
            floor: $profile->resolvedFloor(),
            floorOverride: $profile->floor_override,
            workingRestSeconds: $profile->working_rest_seconds,
            warmUpSteps: $profile->warmUpStepList(),
            displayName: $profile->displayName(),
        );
    }
}
