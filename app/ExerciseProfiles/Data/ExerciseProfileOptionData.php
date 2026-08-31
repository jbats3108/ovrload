<?php

namespace App\ExerciseProfiles\Data;

use App\ExerciseProfiles\Models\ExerciseProfile;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class ExerciseProfileOptionData extends Data
{
    /**
     * @param  list<array{mode?: string, percent?: int, reps: int}>  $warmUpSteps
     * @param  list<array{name: string, slug: string}>  $assignedRoutines
     */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $displayName,
        public readonly string $kind,
        public readonly string $status,
        public readonly int $targetReps,
        public readonly int $floor,
        public readonly ?int $floorOverride,
        public readonly int $workingRestSeconds,
        public readonly array $warmUpSteps,
        public readonly string $recipeFingerprint,
        public readonly string $exerciseFingerprint,
        public readonly string $sharedFingerprint,
        public readonly int $referenceCount = 0,
        public readonly int $staleAssignmentCount = 0,
        public readonly bool $isDefault = false,
        public readonly array $assignedRoutines = [],
    ) {}

    /**
     * @param  list<array{name: string, slug: string}>  $assignedRoutines
     */
    public static function fromProfile(
        ExerciseProfile $profile,
        bool $isDefault = false,
        int $referenceCount = 0,
        int $staleAssignmentCount = 0,
        array $assignedRoutines = [],
    ): self {
        return new self(
            id: $profile->id,
            slug: (string) $profile->slug,
            name: $profile->name,
            displayName: $profile->displayName(),
            kind: $profile->kind->value,
            status: $profile->status->value,
            targetReps: $profile->target_reps,
            floor: $profile->resolvedFloor(),
            floorOverride: $profile->floor_override,
            workingRestSeconds: $profile->working_rest_seconds,
            warmUpSteps: $profile->warmUpStepList(),
            recipeFingerprint: $profile->recipe()->fingerprint(),
            exerciseFingerprint: $profile->recipe()->exerciseFingerprint(),
            sharedFingerprint: $profile->recipe()->sharedFingerprint(),
            referenceCount: $referenceCount,
            staleAssignmentCount: $staleAssignmentCount,
            isDefault: $isDefault,
            assignedRoutines: $assignedRoutines,
        );
    }
}
