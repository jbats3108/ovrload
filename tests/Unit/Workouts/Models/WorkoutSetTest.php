<?php

namespace Tests\Unit\Workouts\Models;

use App\Exercises\Models\Exercise;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Models\WorkoutBlockExercise;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Models\WorkoutSetGroup;
use App\Workouts\Models\WorkoutSetSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutSetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_a_block_exercise(): void
    {
        // Given
        $workoutBlock = WorkoutBlock::create([
            'workout_id' => Workout::factory()->create()->id,
            'position' => 1,
        ]);

        $workoutBlockExercise = WorkoutBlockExercise::create([
            'workout_block_id' => $workoutBlock->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'exercise_name' => 'Test Exercise',
            'working_weight_g' => 80000,
            'prescribed_reps' => 6,
        ]);

        $workoutSetGroup = WorkoutSetGroup::create([
            'workout_block_id' => $workoutBlock->id,
            'type' => 'working',
            'set_count' => 3,
        ]);

        // When
        $workoutSet = WorkoutSet::create([
            'workout_set_group_id' => $workoutSetGroup->id,
            'workout_block_exercise_id' => $workoutBlockExercise->id,
            'set_index' => 0,
        ]);

        // Then
        $this->assertTrue($workoutSet->blockExercise->is($workoutBlockExercise));
    }

    #[Test]
    public function it_allows_recording_reps(): void
    {
        // Given
        $workoutSet = WorkoutSet::factory()->create();

        // When
        $workoutSet->recordReps(6);

        // Then
        $this->assertSame(6, $workoutSet->reps);
    }

    #[Test]
    public function it_allows_recording_weight(): void
    {
        // Given
        $workoutSet = WorkoutSet::factory()->create();

        // When
        $workoutSet->recordWeight(92500);

        // Then
        $this->assertSame(92500, $workoutSet->weight_g);
    }

    #[Test]
    public function it_is_not_a_dropset_with_zero_or_one_segment(): void
    {
        $workoutSet = WorkoutSet::factory()->create();
        $this->assertFalse($workoutSet->isDropset());

        WorkoutSetSegment::create([
            'workout_set_id' => $workoutSet->id,
            'position' => 1,
            'weight_g' => 80000,
        ]);

        $this->assertFalse($workoutSet->fresh()->isDropset());
        $this->assertFalse($workoutSet->fresh()->load('segments')->isDropset());
    }

    #[Test]
    public function it_is_a_dropset_with_two_or_more_segments(): void
    {
        $workoutSet = WorkoutSet::factory()->create();

        WorkoutSetSegment::create([
            'workout_set_id' => $workoutSet->id,
            'position' => 1,
            'weight_g' => 80000,
        ]);
        WorkoutSetSegment::create([
            'workout_set_id' => $workoutSet->id,
            'position' => 2,
            'weight_g' => 60000,
        ]);

        $this->assertTrue($workoutSet->fresh()->isDropset());
        $this->assertTrue($workoutSet->fresh()->load('segments')->isDropset());
    }

    #[Test]
    public function it_clears_all_segments(): void
    {
        $workoutSet = WorkoutSet::factory()->create();

        WorkoutSetSegment::create([
            'workout_set_id' => $workoutSet->id,
            'position' => 1,
            'weight_g' => 80000,
        ]);
        WorkoutSetSegment::create([
            'workout_set_id' => $workoutSet->id,
            'position' => 2,
            'weight_g' => 60000,
        ]);

        $workoutSet->clearSegments();

        $this->assertCount(0, $workoutSet->fresh()->segments);
        $this->assertFalse($workoutSet->fresh()->isDropset());
    }
}
