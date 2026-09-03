<?php

namespace Database\Seeders;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use Illuminate\Database\Seeder;
use RuntimeException;

class ExerciseProfileSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            $recipe = new ExerciseProfileRecipe(
                targetReps: $definition['target_reps'],
                floorOverride: $definition['floor_override'],
                workingRestSeconds: $definition['working_rest_seconds'],
                warmUpSteps: $definition['warm_up_steps'],
            );

            ExerciseProfile::query()->updateOrCreate(
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

    public static function defaultPath(): string
    {
        return database_path('data/exercise-profile-presets.json');
    }

    /**
     * @return list<array{
     *     name: string,
     *     slug: string,
     *     target_reps: int,
     *     floor_override: int|null,
     *     working_rest_seconds: int,
     *     warm_up_steps: list<array{mode?: string, percent?: int, reps: int}>
     * }>
     */
    public static function definitions(): array
    {
        $path = self::defaultPath();
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Preset catalog is not valid JSON: {$path}");
        }

        /** @var list<array{
         *     name: string,
         *     slug: string,
         *     target_reps: int,
         *     floor_override: int|null,
         *     working_rest_seconds: int,
         *     warm_up_steps: list<array{mode?: string, percent?: int, reps: int}>
         * }> $decoded
         */
        return $decoded;
    }
}
