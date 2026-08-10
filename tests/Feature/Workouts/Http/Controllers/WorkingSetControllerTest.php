<?php

namespace Tests\Feature\Workouts\Http\Controllers;

use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class WorkingSetControllerTest extends TestCase
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
    public function owner_can_add_a_working_set_on_in_progress_workout(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $block = $workout->blocks->first();

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->post(route('workouts.working-sets.add', ['workout' => $workout, 'block' => $block]))
            ->assertRedirect(route('workouts.play', $workout));
    }

    #[Test]
    public function owner_can_remove_a_working_set_on_in_progress_workout(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->orderByDesc('set_index')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->delete(route('workouts.sets.remove', ['workout' => $workout, 'set' => $set]))
            ->assertRedirect(route('workouts.play', $workout));
    }

    #[Test]
    public function non_owner_cannot_add_working_sets(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $block = $workout->blocks->first();

        $this->actingAs($this->secondUser)
            ->post(route('workouts.working-sets.add', ['workout' => $workout, 'block' => $block]))
            ->assertForbidden();
    }

    #[Test]
    public function finished_workout_cannot_add_working_sets(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $workout->update(['status' => WorkoutStatus::Finished]);
        $block = $workout->blocks->first();

        $this->actingAs($this->user)
            ->post(route('workouts.working-sets.add', ['workout' => $workout, 'block' => $block]))
            ->assertForbidden();
    }

    #[Test]
    public function add_rejects_block_from_another_workout(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $other = $this->createPlayableWorkout(user: $this->secondUser, setCount: 2, loadBlocks: true);
        $foreignBlock = $other->blocks->first();

        $this->actingAs($this->user)
            ->post(route('workouts.working-sets.add', ['workout' => $workout, 'block' => $foreignBlock]))
            ->assertNotFound();
    }

    #[Test]
    public function remove_rejects_set_from_another_workout(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $other = $this->createPlayableWorkout(user: $this->secondUser, setCount: 2, loadBlocks: true);
        $foreignSet = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $other->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('workouts.sets.remove', ['workout' => $workout, 'set' => $foreignSet]))
            ->assertNotFound();
    }

    #[Test]
    public function owner_can_skip_rest_of_block_on_in_progress_workout(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 3, loadBlocks: true);
        $block = $workout->blocks->first();
        $first = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->where('set_index', 0)
            ->firstOrFail();
        $first->update(['completed_at' => now(), 'reps' => 5, 'weight_g' => 80000]);

        $this->actingAs($this->user)
            ->from(route('workouts.play', $workout))
            ->post(route('workouts.blocks.skip-rest', ['workout' => $workout, 'block' => $block]))
            ->assertRedirect(route('workouts.play', $workout));

        $working = $block->fresh()->workingSetGroup;
        $this->assertSame(1, $working->set_count);
        $this->assertCount(1, $working->sets);
    }

    #[Test]
    public function non_owner_cannot_skip_rest_of_block(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $block = $workout->blocks->first();

        $this->actingAs($this->secondUser)
            ->post(route('workouts.blocks.skip-rest', ['workout' => $workout, 'block' => $block]))
            ->assertForbidden();
    }

    #[Test]
    public function finished_workout_cannot_skip_rest_of_block(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $workout->update(['status' => WorkoutStatus::Finished]);
        $block = $workout->blocks->first();

        $this->actingAs($this->user)
            ->post(route('workouts.blocks.skip-rest', ['workout' => $workout, 'block' => $block]))
            ->assertForbidden();
    }

    #[Test]
    public function skip_rest_rejects_block_from_another_workout(): void
    {
        $workout = $this->createPlayableWorkout(setCount: 2, loadBlocks: true);
        $other = $this->createPlayableWorkout(user: $this->secondUser, setCount: 2, loadBlocks: true);
        $foreignBlock = $other->blocks->first();

        $this->actingAs($this->user)
            ->post(route('workouts.blocks.skip-rest', ['workout' => $workout, 'block' => $foreignBlock]))
            ->assertNotFound();
    }
}
