<?php

namespace Database\Seeders;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use App\Users\Enums\WarmUpDefaultsScope;
use App\Users\Models\User;
use Illuminate\Database\Seeder;

class LocalExerciseProfileSeeder extends Seeder
{
    /** @var list<string> */
    private const array SEEDED_USER_EMAILS = [
        'admin1@test.com',
        'admin2@test.com',
        'user1@test.com',
        'user3@test.com',
    ];

    public function run(): void
    {
        $strength = ExerciseProfile::query()
            ->where('slug_scope', 'system')
            ->where('slug', 'preset-strength')
            ->firstOrFail();

        User::query()
            ->whereIn('email', self::SEEDED_USER_EMAILS)
            ->update([
                'default_exercise_profile_id' => $strength->id,
                'achievement_floor_default' => $strength->resolvedFloor(),
                'progression_target_default' => $strength->target_reps,
                'warm_up_steps_default' => $strength->warmUpStepList(),
                'warm_up_defaults_scope' => WarmUpDefaultsScope::FirstBlock->value,
            ]);

        $user = User::query()->where('email', 'user1@test.com')->first();
        if ($user === null) {
            return;
        }

        $this->upsertCustomProfile(
            $user,
            slug: 'power-builder',
            name: 'Power Builder',
            targetReps: 8,
            floorOverride: null,
            workingRestSeconds: 120,
            warmUpSteps: [
                ['percent' => 50, 'reps' => 5],
                ['percent' => 70, 'reps' => 3],
            ],
        );
        $this->upsertCustomProfile(
            $user,
            slug: 'accessory-volume',
            name: 'Accessory Volume',
            targetReps: 12,
            floorOverride: null,
            workingRestSeconds: 90,
            warmUpSteps: [],
        );
    }

    /**
     * @param  list<array{percent: int, reps: int}>  $warmUpSteps
     */
    private function upsertCustomProfile(
        User $user,
        string $slug,
        string $name,
        int $targetReps,
        ?int $floorOverride,
        int $workingRestSeconds,
        array $warmUpSteps,
    ): void {
        $recipe = new ExerciseProfileRecipe(
            targetReps: $targetReps,
            floorOverride: $floorOverride,
            workingRestSeconds: $workingRestSeconds,
            warmUpSteps: $warmUpSteps,
        );

        ExerciseProfile::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'slug' => $slug,
            ],
            [
                'created_by_user_id' => null,
                'kind' => ExerciseProfileKind::Custom,
                'status' => ExerciseProfileStatus::Published,
                'name' => $name,
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
