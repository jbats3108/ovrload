<?php

namespace Tests\Feature\Workouts\Http\Controllers;

use App\Exercises\Models\Exercise;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class AdHocExerciseControllerTest extends TestCase
{
    use CreatesPlayableWorkout;
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(false);
    }

    #[Test]
    public function owner_can_add_an_ad_hoc_exercise_to_an_in_progress_workout(): void
    {
        $workout = $this->createPlayableWorkout(
            setCount: 1,
            loadBlocks: true,
            prescribedReps: 8,
        );
        $exercise = Exercise::factory()->shared()->create(['name' => 'Cable Curl']);

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ])
            ->assertRedirect(route('workouts.play', $workout));

        $workout->load([
            'blocks.blockExercises',
            'blocks.setGroups.sets',
        ]);
        $adHocBlock = $workout->blocks->firstWhere('is_ad_hoc', true);
        $adHocExercise = $adHocBlock?->blockExercises->first();
        $workingGroup = $adHocBlock?->setGroups->firstWhere('type', SetGroupType::Working);

        $this->assertNotNull($adHocBlock);
        $this->assertSame(2, $adHocBlock->position);
        $this->assertFalse($adHocBlock->is_superset);
        $this->assertSame($exercise->id, $adHocExercise?->exercise_id);
        $this->assertSame(0, $adHocExercise?->working_weight_g);
        $this->assertSame(6, $adHocExercise?->prescribed_reps);
        $this->assertNull($adHocExercise?->progression_target);
        $this->assertSame(3, $workingGroup?->set_count);
        $this->assertSame(120, $workingGroup?->rest_seconds);
        $this->assertCount(3, $workingGroup?->sets);
        $this->assertCount(2, $workout->blocks);
    }

    #[Test]
    public function ad_hoc_exercise_uses_training_default_target_reps_not_previous_block(): void
    {
        $this->user->update(['progression_target_default' => 10]);
        $workout = $this->createPlayableWorkout(
            setCount: 1,
            loadBlocks: true,
            prescribedReps: 8,
        );
        $exercise = Exercise::factory()->shared()->create(['name' => 'Face Pull']);

        $this->actingAs($this->user)
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ])
            ->assertRedirect();

        $adHocExercise = $workout->fresh(['blocks.blockExercises'])
            ->blocks
            ->firstWhere('is_ad_hoc', true)
            ?->blockExercises
            ->first();

        $this->assertSame(10, $adHocExercise?->prescribed_reps);
    }

    #[Test]
    public function owner_can_add_a_custom_exercise_to_an_in_progress_workout(): void
    {
        $workout = $this->createPlayableWorkout();
        $exercise = Exercise::factory()->custom($this->user)->create(['name' => 'My Custom Curl']);

        $this->actingAs($this->user)
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workout_block_exercises', [
            'exercise_id' => $exercise->id,
            'exercise_name' => 'My Custom Curl',
        ]);
    }

    #[Test]
    public function owner_cannot_add_another_users_custom_exercise(): void
    {
        $workout = $this->createPlayableWorkout();
        $exercise = Exercise::factory()->custom($this->secondUser)->create();

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ])
            ->assertRedirect(route('workouts.play', $workout))
            ->assertSessionHasErrors('workout', WorkoutService::AD_HOC_EXERCISE_NOT_AVAILABLE_ERROR);

        $this->assertDatabaseMissing('workout_block_exercises', [
            'exercise_id' => $exercise->id,
        ]);
    }

    #[Test]
    public function non_owner_cannot_add_an_ad_hoc_exercise(): void
    {
        $workout = $this->createPlayableWorkout();
        $exercise = Exercise::factory()->shared()->create();

        $this->actingAs($this->secondUser)
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function finished_workout_cannot_add_an_ad_hoc_exercise(): void
    {
        $workout = $this->createPlayableWorkout();
        $workout->update(['status' => 'finished']);
        $exercise = Exercise::factory()->shared()->create();

        $this->actingAs($this->user)
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_remove_an_empty_ad_hoc_block(): void
    {
        $workout = $this->createPlayableWorkout(loadBlocks: true);
        $exercise = Exercise::factory()->shared()->create();

        $this->actingAs($this->user)
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ]);

        $adHocBlock = $workout->fresh()->blocks->firstWhere('is_ad_hoc', true);

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->delete(route('workouts.ad-hoc-blocks.destroy', ['workout' => $workout, 'block' => $adHocBlock]))
            ->assertRedirect(route('workouts.play', $workout));

        $this->assertDatabaseMissing('workout_blocks', ['id' => $adHocBlock->id]);
        $this->assertDatabaseMissing('workout_block_exercises', ['workout_block_id' => $adHocBlock->id]);
    }

    #[Test]
    public function logged_ad_hoc_block_cannot_be_removed(): void
    {
        $workout = $this->createPlayableWorkout(loadBlocks: true);
        $exercise = Exercise::factory()->shared()->create();

        $this->actingAs($this->user)
            ->post(route('workouts.ad-hoc-exercises.store', $workout), [
                'exercise_id' => $exercise->id,
            ]);

        $adHocBlock = $workout->fresh()->blocks->firstWhere('is_ad_hoc', true);
        $set = WorkoutSet::query()
            ->whereHas('setGroup', fn ($query) => $query
                ->where('workout_block_id', $adHocBlock->id)
                ->where('type', SetGroupType::Working))
            ->firstOrFail();
        app(WorkoutService::class)->completeSet($set, reps: 6, weightGrams: 80000);

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->delete(route('workouts.ad-hoc-blocks.destroy', ['workout' => $workout, 'block' => $adHocBlock]))
            ->assertRedirect(route('workouts.play', $workout))
            ->assertSessionHasErrors('workout', WorkoutService::AD_HOC_BLOCK_HAS_LOGGED_SETS_ERROR);

        $this->assertDatabaseHas('workout_blocks', ['id' => $adHocBlock->id]);
    }

    #[Test]
    public function a_routine_block_cannot_be_removed_as_ad_hoc(): void
    {
        $workout = $this->createPlayableWorkout(loadBlocks: true);
        $routineBlock = $workout->blocks->first();

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->delete(route('workouts.ad-hoc-blocks.destroy', ['workout' => $workout, 'block' => $routineBlock]))
            ->assertRedirect(route('workouts.play', $workout))
            ->assertSessionHasErrors('workout', WorkoutService::AD_HOC_BLOCK_ONLY_ERROR);

        $this->assertDatabaseHas('workout_blocks', ['id' => $routineBlock->id]);
    }
}
