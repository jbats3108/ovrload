<?php

namespace Tests\Feature\Shared\Http;

use App\Routines\Models\Routine;
use App\Workouts\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class SoftFailTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(false);
    }

    #[Test]
    public function missing_workout_play_redirects_authenticated_user_with_flash(): void
    {
        $this->actingAs($this->user)
            ->get(route('workouts.play', ['workout' => '01INVALIDULIDNOTFOUND00000']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Workout not found. Check the URL and try again.');
    }

    #[Test]
    public function missing_routine_redirects_with_resource_flash(): void
    {
        $this->actingAs($this->user)
            ->get(route('routines.show', ['routine' => 'does-not-exist']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Routine not found. Check the URL and try again.');
    }

    #[Test]
    public function other_users_workout_play_soft_fails_as_not_found(): void
    {
        $workout = Workout::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->secondUser)
            ->get(route('workouts.play', $workout))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Workout not found. Check the URL and try again.');
    }

    #[Test]
    public function other_users_routine_soft_fails_as_not_found(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();

        $this->actingAs($this->secondUser)
            ->get(route('routines.show', $routine))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Routine not found. Check the URL and try again.');
    }

    #[Test]
    public function guest_missing_route_still_returns_not_found(): void
    {
        $this->get('/register')->assertNotFound();
    }

    #[Test]
    public function admin_forbidden_stays_hard(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    #[Test]
    public function mutation_on_other_users_workout_is_not_found(): void
    {
        $workout = Workout::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->secondUser)
            ->post(route('workouts.finish', $workout))
            ->assertNotFound();
    }
}
