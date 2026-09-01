<?php

namespace Tests\Feature\Workouts\Http\Controllers;

use App\Workouts\Enums\WorkoutStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class DiscardWorkoutControllerTest extends TestCase
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
    public function it_discards_an_in_progress_workout(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->post(route('workouts.discard', $workout))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertSame(WorkoutStatus::Discarded, $workout->fresh()->status);
    }

    #[Test]
    public function it_forbids_other_users(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->secondUser)
            ->post(route('workouts.discard', $workout))
            ->assertNotFound();
    }
}
