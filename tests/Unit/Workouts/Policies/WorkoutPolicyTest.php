<?php

namespace Tests\Unit\Workouts\Policies;

use App\Users\Models\User;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Models\Workout;
use App\Workouts\Policies\WorkoutPolicy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutPolicyTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->policy = new WorkoutPolicy;
    }

    #[Test]
    public function the_owner_can_apply_progression_on_the_latest_finished_workout(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($user);

        $this->assertTrue($this->policy->applyProgression($user, $workout));
    }

    #[Test]
    public function another_user_cannot_apply_progression_on_a_finished_workout(): void
    {
        $owner = User::factory()->withRole('user')->create();
        $other = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($owner);

        $this->assertFalse($this->policy->applyProgression($other, $workout));
    }

    #[Test]
    public function the_owner_cannot_apply_progression_on_an_in_progress_workout(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutStatus::InProgress,
        ]);
        $workout->setRelation('user', $user);

        $this->assertFalse($this->policy->applyProgression($user, $workout));
    }

    #[Test]
    public function the_owner_can_dismiss_progression_on_a_finished_workout(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($user);

        $this->assertTrue($this->policy->dismissProgression($user, $workout));
    }

    #[Test]
    public function another_user_cannot_dismiss_progression_on_a_finished_workout(): void
    {
        $owner = User::factory()->withRole('user')->create();
        $other = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($owner);

        $this->assertFalse($this->policy->dismissProgression($other, $workout));
    }

    #[Test]
    public function the_owner_cannot_dismiss_progression_on_an_in_progress_workout(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutStatus::InProgress,
        ]);
        $workout->setRelation('user', $user);

        $this->assertFalse($this->policy->dismissProgression($user, $workout));
    }

    #[Test]
    public function the_owner_can_edit_finished_history(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($user);

        $this->assertTrue($this->policy->editHistory($user, $workout));
    }

    #[Test]
    public function another_user_cannot_edit_finished_history(): void
    {
        $owner = User::factory()->withRole('user')->create();
        $other = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($owner);

        $this->assertFalse($this->policy->editHistory($other, $workout));
    }

    #[Test]
    public function the_owner_cannot_edit_in_progress_history(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutStatus::InProgress,
        ]);
        $workout->setRelation('user', $user);

        $this->assertFalse($this->policy->editHistory($user, $workout));
    }

    #[Test]
    public function the_owner_can_delete_finished_history(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($user);

        $this->assertTrue($this->policy->deleteHistory($user, $workout));
    }

    #[Test]
    public function another_user_cannot_delete_finished_history(): void
    {
        $owner = User::factory()->withRole('user')->create();
        $other = User::factory()->withRole('user')->create();
        $workout = $this->finishedWorkoutFor($owner);

        $this->assertFalse($this->policy->deleteHistory($other, $workout));
    }

    #[Test]
    public function the_owner_cannot_delete_in_progress_history(): void
    {
        $user = User::factory()->withRole('user')->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutStatus::InProgress,
        ]);
        $workout->setRelation('user', $user);

        $this->assertFalse($this->policy->deleteHistory($user, $workout));
    }

    private function finishedWorkoutFor(User $user): Workout
    {
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutStatus::Finished,
            'started_at' => Carbon::now()->subHour(),
            'finished_at' => Carbon::now(),
        ]);
        $workout->setRelation('user', $user);

        return $workout;
    }
}
