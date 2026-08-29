<?php

namespace Database\Seeders;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfilePresetCatalog;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use Illuminate\Database\Seeder;

class ExerciseProfileSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ExerciseProfilePresetCatalog::definitions() as $definition) {
            $recipe = new ExerciseProfileRecipe(
                targetReps: $definition['target_reps'],
                floorOverride: $definition['floor_override'],
                workingRestSeconds: $definition['working_rest_seconds'],
                warmUpSteps: $definition['warm_up_steps'],
            );

            ExerciseProfile::query()->firstOrCreate(
                [
                    'slug_scope' => 'system',
                    'slug' => $definition['slug'],
                ],
                [
                    'user_id' => null,
                    'created_by_user_id' => null,
                    'kind' => ExerciseProfileKind::Preset,
                    'status' => ExerciseProfileStatus::Published,
                    'name' => $definition['name'],
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
}
