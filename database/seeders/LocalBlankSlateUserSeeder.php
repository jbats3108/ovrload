<?php

namespace Database\Seeders;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use App\Users\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dev fixture: user2@test.com with no default profile and one unused custom profile.
 *
 * Clears any default assigned by LocalExerciseProfileSeeder so Preferences /
 * create-routine flows can exercise the empty-default path.
 */
final class LocalBlankSlateUserSeeder extends Seeder
{
    public const string EMAIL = 'user2@test.com';

    public function run(): void
    {
        $user = User::query()->where('email', self::EMAIL)->first();
        if ($user === null) {
            return;
        }

        $user->forceFill(['default_exercise_profile_id' => null])->save();

        $user->exerciseProfiles()->each(static fn (ExerciseProfile $profile): bool => (bool) $profile->forceDelete());

        $recipe = new ExerciseProfileRecipe(
            targetReps: 8,
            floorOverride: null,
            workingRestSeconds: 120,
            warmUpSteps: [
                ['mode' => 'percent', 'percent' => 50, 'reps' => 5],
            ],
        );

        ExerciseProfile::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'slug' => 'deletable-test',
            ],
            [
                'created_by_user_id' => null,
                'kind' => ExerciseProfileKind::Custom,
                'status' => ExerciseProfileStatus::Published,
                'name' => 'Deletable Test',
                'slug_scope' => 'user-'.$user->id,
                'target_reps' => $recipe->targetReps,
                'floor_override' => $recipe->floorOverride,
                'working_rest_seconds' => $recipe->workingRestSeconds,
                'warm_up_steps' => $recipe->warmUpSteps,
                'recipe_fingerprint' => $recipe->fingerprint(),
                'published_at' => now(),
            ],
        );
    }
}
