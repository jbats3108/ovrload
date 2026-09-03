<?php

namespace Tests\Feature\ExerciseProfiles;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use Database\Seeders\ExerciseProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseProfileSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_loads_the_three_published_presets_from_json(): void
    {
        $definitions = ExerciseProfileSeeder::definitions();

        $this->assertSame(
            ['Strength', 'Hypertrophy', 'Endurance'],
            array_column($definitions, 'name'),
        );
        $this->assertSame(
            [
                'preset-strength',
                'preset-hypertrophy',
                'preset-endurance',
            ],
            array_column($definitions, 'slug'),
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

        $floors = array_map(
            static fn (array $definition): int => new ExerciseProfileRecipe(
                targetReps: $definition['target_reps'],
                floorOverride: $definition['floor_override'],
                workingRestSeconds: $definition['working_rest_seconds'],
                warmUpSteps: $definition['warm_up_steps'],
            )->resolvedFloor(),
            $definitions,
        );

        $this->assertSame([4, 8, 15], $floors);
    }

    #[Test]
    public function it_is_idempotent_when_reseeded(): void
    {
        $this->assertSame(3, ExerciseProfile::query()->where('kind', ExerciseProfileKind::Preset)->count());

        (new ExerciseProfileSeeder)->run();

        $this->assertSame(3, ExerciseProfile::query()->where('kind', ExerciseProfileKind::Preset)->count());
        $this->assertDatabaseHas('exercise_profiles', [
            'slug' => 'preset-strength',
            'kind' => ExerciseProfileKind::Preset->value,
            'name' => 'Strength',
        ]);
    }
}
