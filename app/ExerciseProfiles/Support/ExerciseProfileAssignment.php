<?php

namespace App\ExerciseProfiles\Support;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;

final class ExerciseProfileAssignment
{
    public static function assignmentIsCurrent(?string $stored, string $current): bool
    {
        return $stored === null || $stored === $current;
    }

    public static function exerciseFingerprint(?ExerciseProfile $profile, bool $useSupersetRules): ?string
    {
        if ($profile === null) {
            return null;
        }

        return $useSupersetRules
            ? $profile->recipe()->exerciseFingerprint()
            : $profile->recipe()->fingerprint();
    }

    public static function storedExerciseFingerprint(?string $incoming, ?string $computed): ?string
    {
        return $incoming ?? $computed;
    }

    public static function sharedProfileFingerprint(?ExerciseProfile $profile, ?string $incoming): ?string
    {
        if ($profile === null) {
            return null;
        }

        return $incoming ?? $profile->recipe()->sharedFingerprint();
    }

    public static function expectedExerciseFingerprint(
        ExerciseProfileRecipe $recipe,
        bool $isSuperset,
        bool $sharedAssignedToSameProfile,
    ): string {
        return $isSuperset || ! $sharedAssignedToSameProfile
            ? $recipe->exerciseFingerprint()
            : $recipe->fingerprint();
    }
}
