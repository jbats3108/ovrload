<?php

namespace Database\Factories\ExerciseProfiles\Models;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseProfile>
 */
class ExerciseProfileFactory extends Factory
{
    protected $model = ExerciseProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recipe = new ExerciseProfileRecipe(
            targetReps: 6,
            floorOverride: null,
            workingRestSeconds: 120,
            warmUpSteps: [
                ['percent' => 50, 'reps' => 5],
            ],
        );

        return [
            'user_id' => User::factory(),
            'created_by_user_id' => null,
            'kind' => ExerciseProfileKind::Custom,
            'status' => ExerciseProfileStatus::Published,
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'slug_scope' => 'factory-user',
            'target_reps' => $recipe->targetReps,
            'floor_override' => $recipe->floorOverride,
            'working_rest_seconds' => $recipe->workingRestSeconds,
            'warm_up_steps' => $recipe->warmUpSteps,
            'recipe_fingerprint' => $recipe->fingerprint(),
            'published_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'slug_scope' => 'user-'.$user->id,
        ]);
    }

    public function withRecipe(ExerciseProfileRecipe $recipe): static
    {
        return $this->state(fn (): array => [
            'target_reps' => $recipe->targetReps,
            'floor_override' => $recipe->floorOverride,
            'working_rest_seconds' => $recipe->workingRestSeconds,
            'warm_up_steps' => $recipe->warmUpSteps,
            'recipe_fingerprint' => $recipe->fingerprint(),
        ]);
    }

    public function preset(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'kind' => ExerciseProfileKind::Preset,
            'status' => ExerciseProfileStatus::Published,
            'slug_scope' => 'system',
            'created_by_user_id' => User::factory(),
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'kind' => ExerciseProfileKind::Preset,
            'status' => ExerciseProfileStatus::Draft,
            'slug' => null,
            'published_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => ExerciseProfileStatus::Archived,
        ]);
    }
}
