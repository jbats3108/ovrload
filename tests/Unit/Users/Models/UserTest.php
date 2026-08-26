<?php

namespace Tests\Unit\Users\Models;

use App\Routines\Models\Routine;
use App\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    #[Test]
    public function it_can_be_identified_as_an_admin(): void
    {
        // Given
        $this->seed(RoleSeeder::class);

        // When
        $user = User::factory()->withRole('admin')->create();

        // Then
        $this->assertTrue($user->isAdmin());

    }

    #[Test]
    public function it_can_be_identified_as_not_an_admin(): void
    {
        // Given
        $this->seed(RoleSeeder::class);

        // When
        $user = User::factory()->withRole('user')->create();

        // Then
        $this->assertFalse($user->isAdmin());

    }

    #[Test]
    public function it_is_linked_to_routines(): void
    {
        // Given
        $this->seed(RoleSeeder::class);
        $user = $this->createUser('user');

        $routines = Routine::factory(3)->withUser($user)->create();

        // When
        $userRoutines = $user->routines;

        // Then
        $this->assertEqualsCanonicalizing(
            $routines->pluck('id')->all(),
            $userRoutines->pluck('id')->all(),
        );

    }

    #[Test]
    public function resolved_warm_up_steps_default_uses_fallback_when_null(): void
    {
        $user = User::factory()->create(['warm_up_steps_default' => null]);

        $this->assertSame(User::fallbackWarmUpSteps(), $user->resolvedWarmUpStepsDefault());
    }

    #[Test]
    public function resolved_warm_up_steps_default_returns_empty_when_empty_list(): void
    {
        $user = User::factory()->create(['warm_up_steps_default' => []]);

        $this->assertSame([], $user->resolvedWarmUpStepsDefault());
    }

    #[Test]
    public function resolved_default_target_reps_falls_back_to_six_when_null(): void
    {
        $user = User::factory()->create(['progression_target_default' => null]);

        $this->assertSame(6, $user->resolvedDefaultTargetReps());
    }

    #[Test]
    public function resolved_default_target_reps_uses_stored_value(): void
    {
        $user = User::factory()->create(['progression_target_default' => 10]);

        $this->assertSame(10, $user->resolvedDefaultTargetReps());
    }

    #[Test]
    public function resolved_warm_up_steps_default_returns_custom_steps(): void
    {
        $custom = [
            ['percent' => 50, 'reps' => 8],
            ['percent' => 70, 'reps' => 4],
        ];
        $user = User::factory()->create(['warm_up_steps_default' => $custom]);

        $this->assertSame($custom, $user->resolvedWarmUpStepsDefault());
    }

    #[Test]
    public function fallback_warm_up_steps_matches_expected_defaults(): void
    {
        $this->assertSame([
            ['percent' => 40, 'reps' => 5],
            ['percent' => 60, 'reps' => 3],
            ['percent' => 80, 'reps' => 1],
        ], User::fallbackWarmUpSteps());
    }
}
