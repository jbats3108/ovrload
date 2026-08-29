<?php

namespace Tests\Unit\ExerciseProfiles;

use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExerciseProfileRecipeTest extends TestCase
{
    #[Test]
    public function it_derives_the_floor_from_the_target(): void
    {
        $recipe = new ExerciseProfileRecipe(
            targetReps: 6,
            floorOverride: null,
            workingRestSeconds: 120,
            warmUpSteps: [],
        );

        $this->assertSame(4, $recipe->resolvedFloor());
    }

    #[Test]
    public function it_never_derives_a_floor_below_one_rep(): void
    {
        $recipe = new ExerciseProfileRecipe(
            targetReps: 1,
            floorOverride: null,
            workingRestSeconds: 120,
            warmUpSteps: [],
        );

        $this->assertSame(1, $recipe->resolvedFloor());
    }

    #[Test]
    public function it_preserves_an_explicit_floor_override(): void
    {
        $recipe = new ExerciseProfileRecipe(
            targetReps: 10,
            floorOverride: 6,
            workingRestSeconds: 120,
            warmUpSteps: [],
        );

        $this->assertSame(6, $recipe->resolvedFloor());
    }

    #[Test]
    public function it_fingerprints_the_complete_recipe_in_order(): void
    {
        $first = new ExerciseProfileRecipe(
            targetReps: 10,
            floorOverride: null,
            workingRestSeconds: 90,
            warmUpSteps: [
                ['percent' => 50, 'reps' => 10],
                ['percent' => 80, 'reps' => 5],
            ],
        );
        $same = new ExerciseProfileRecipe(
            targetReps: 10,
            floorOverride: null,
            workingRestSeconds: 90,
            warmUpSteps: [
                ['percent' => 50, 'reps' => 10],
                ['percent' => 80, 'reps' => 5],
            ],
        );
        $differentOrder = new ExerciseProfileRecipe(
            targetReps: 10,
            floorOverride: null,
            workingRestSeconds: 90,
            warmUpSteps: [
                ['percent' => 80, 'reps' => 5],
                ['percent' => 50, 'reps' => 10],
            ],
        );

        $this->assertSame($first->fingerprint(), $same->fingerprint());
        $this->assertNotSame($first->fingerprint(), $differentOrder->fingerprint());
    }

    #[Test]
    public function it_has_a_separate_exercise_fingerprint_for_superset_assignments(): void
    {
        $first = new ExerciseProfileRecipe(
            targetReps: 10,
            floorOverride: null,
            workingRestSeconds: 90,
            warmUpSteps: [
                ['percent' => 50, 'reps' => 10],
            ],
        );
        $second = new ExerciseProfileRecipe(
            targetReps: 10,
            floorOverride: null,
            workingRestSeconds: 180,
            warmUpSteps: [
                ['percent' => 50, 'reps' => 5],
            ],
        );

        $this->assertSame($first->exerciseFingerprint(), $second->exerciseFingerprint());
    }
}
