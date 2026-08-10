<?php

namespace Tests\Unit\Exercises\Services;

use App\Exercises\Models\Exercise;
use App\Exercises\Services\CustomExerciseSlugGenerator;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomExerciseSlugGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_slugs_the_name(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            'cable-pull-through',
            CustomExerciseSlugGenerator::forUser($user, 'Cable Pull-Through'),
        );
    }

    #[Test]
    public function it_suffixes_on_collision_within_the_same_user(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->custom($user)->create([
            'slug' => 'cable-pull-through',
        ]);

        $this->assertSame(
            'cable-pull-through-2',
            CustomExerciseSlugGenerator::forUser($user, 'Cable Pull-Through'),
        );
    }

    #[Test]
    public function it_allows_the_same_slug_for_a_different_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Exercise::factory()->custom($owner)->create([
            'slug' => 'cable-pull-through',
        ]);

        $this->assertSame(
            'cable-pull-through',
            CustomExerciseSlugGenerator::forUser($other, 'Cable Pull-Through'),
        );
    }

    #[Test]
    public function it_falls_back_when_the_name_slugs_to_empty(): void
    {
        $user = User::factory()->create();

        $this->assertSame('exercise', CustomExerciseSlugGenerator::forUser($user, '!!!'));
    }

    #[Test]
    public function it_suffixes_when_a_soft_deleted_slug_still_occupies_the_unique_index(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->custom($user)->create([
            'slug' => 'cable-pull-through',
        ]);
        $exercise->delete();

        $this->assertSame(
            'cable-pull-through-2',
            CustomExerciseSlugGenerator::forUser($user, 'Cable Pull-Through'),
        );
    }
}
