<?php

namespace Tests\Unit\ExerciseProfiles;

use App\ExerciseProfiles\Services\ExerciseProfilePresetCatalog;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExerciseProfilePresetCatalogTest extends TestCase
{
    #[Test]
    public function it_defines_the_three_initial_presets(): void
    {
        $this->assertSame(
            ['Strength', 'Hypertrophy', 'Endurance'],
            array_column(ExerciseProfilePresetCatalog::definitions(), 'name'),
        );
    }

    #[Test]
    public function it_uses_unique_preset_slugs_and_expected_recipe_values(): void
    {
        $definitions = ExerciseProfilePresetCatalog::definitions();
        $slugs = array_column($definitions, 'slug');

        $this->assertCount(3, array_unique($slugs));
        $this->assertSame(
            [
                'preset-strength',
                'preset-hypertrophy',
                'preset-endurance',
            ],
            $slugs,
        );
        $this->assertSame(
            [
                ['target_reps' => 6, 'floor_override' => null, 'working_rest_seconds' => 180],
                ['target_reps' => 10, 'floor_override' => null, 'working_rest_seconds' => 90],
                ['target_reps' => 17, 'floor_override' => null, 'working_rest_seconds' => 60],
            ],
            array_map(
                static fn (array $definition): array => [
                    'target_reps' => $definition['target_reps'],
                    'floor_override' => $definition['floor_override'],
                    'working_rest_seconds' => $definition['working_rest_seconds'],
                ],
                $definitions,
            ),
        );
    }

    #[Test]
    public function preset_recipes_derive_the_expected_floors(): void
    {
        $floors = array_map(
            static fn (array $definition): int => new ExerciseProfileRecipe(
                targetReps: $definition['target_reps'],
                floorOverride: $definition['floor_override'],
                workingRestSeconds: $definition['working_rest_seconds'],
                warmUpSteps: $definition['warm_up_steps'],
            )->resolvedFloor(),
            ExerciseProfilePresetCatalog::definitions(),
        );

        $this->assertSame([4, 8, 15], $floors);
    }
}
