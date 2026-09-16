<?php

namespace Tests\Feature\Exercises;

use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_loads_the_default_shared_catalog_from_json(): void
    {
        $this->seed(ExerciseSeeder::class);

        $sharedCount = Exercise::shared()->count();
        $this->assertGreaterThanOrEqual(150, $sharedCount);
        $this->assertLessThanOrEqual(220, $sharedCount);
        $this->assertGreaterThan(8, MuscleGroup::count());

        $this->assertDatabaseHas('exercises', [
            'slug' => 'barbell-deadlift',
            'user_id' => null,
            'equipment' => 'barbell',
        ]);
        $this->assertDatabaseHas('exercises', [
            'slug' => 'arnold-dumbbell-press',
            'equipment' => 'dumbbell',
        ]);
        $this->assertDatabaseHas('exercises', [
            'slug' => 'barbell-bench-press',
            'name' => 'Barbell Bench Press',
            'equipment' => 'barbell',
        ]);
    }

    #[Test]
    public function it_is_idempotent_on_reseed(): void
    {
        $this->seed(ExerciseSeeder::class);
        $count = Exercise::shared()->count();

        $this->seed(ExerciseSeeder::class);

        $this->assertSame($count, Exercise::shared()->count());
    }

    #[Test]
    public function it_restores_soft_deleted_shared_exercises_on_reseed(): void
    {
        $this->seed(ExerciseSeeder::class);

        $exercise = Exercise::query()->shared()->where('slug', 'barbell-deadlift')->firstOrFail();
        $exercise->delete();
        $this->assertSoftDeleted($exercise);

        $this->seed(ExerciseSeeder::class);

        $this->assertNotSoftDeleted($exercise->fresh());
        $this->assertSame('Barbell Deadlift', $exercise->fresh()->name);
    }

    #[Test]
    public function reseed_does_not_prune_shared_exercises_absent_from_json(): void
    {
        $this->seed(ExerciseSeeder::class);

        $orphan = Exercise::factory()->create([
            'user_id' => null,
            'slug' => 'orphan-press',
            'name' => 'Orphan Press',
        ]);

        $this->seed(ExerciseSeeder::class);

        $this->assertNotSoftDeleted($orphan->fresh());
        $this->assertDatabaseHas('exercises', [
            'slug' => 'orphan-press',
            'user_id' => null,
            'deleted_at' => null,
        ]);
    }
}
