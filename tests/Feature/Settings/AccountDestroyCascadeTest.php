<?php

namespace Tests\Feature\Settings;

use App\Auth\Models\RegistrationInvite;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Users\Models\User;
use App\Users\Services\PlateProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountDestroyCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deleting_account_removes_custom_exercises_instead_of_sharing_them(): void
    {
        $user = User::factory()->create();
        $custom = Exercise::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Custom Lift',
            'slug' => 'my-custom-lift',
        ]);
        $shared = Exercise::factory()->create([
            'user_id' => null,
            'name' => 'Shared Bench',
            'slug' => 'shared-bench',
        ]);

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertDatabaseMissing('exercises', ['id' => $custom->id]);
        $this->assertNull(Exercise::withTrashed()->find($custom->id));
        $this->assertSame(0, Exercise::query()->shared()->where('slug', 'my-custom-lift')->count());
        $this->assertNotNull($shared->fresh());
    }

    #[Test]
    public function deleting_account_removes_routines_workouts_and_plate_profile(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $exerciseProfile = ExerciseProfile::factory()->forUser($user)->create();
        $user->forceFill(['default_exercise_profile_id' => $exerciseProfile->id])->save();
        $routine->forceFill(['default_exercise_profile_id' => $exerciseProfile->id])->save();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 80000,
            'prescribed_reps' => 5,
        ]);

        app(PlateProfileService::class)->ensureProfile($user);
        $this->assertNotNull($user->fresh()->plateProfile);

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertNull(Routine::withTrashed()->find($routine->id));
        $this->assertDatabaseMissing('routine_blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
        $this->assertDatabaseMissing('plate_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('exercise_profiles', ['id' => $exerciseProfile->id]);
    }

    #[Test]
    public function deleting_account_clears_sessions_password_resets_and_related_invites(): void
    {
        $user = User::factory()->create(['email' => 'deleteme@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);

        DB::table('sessions')->insert([
            'id' => 'session-for-user',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => 'reset-token',
            'created_at' => now(),
        ]);

        $inviteToUser = RegistrationInvite::create([
            'token' => 'invite-to-user',
            'created_by' => $other->id,
            'role' => 'user',
            'email' => $user->email,
            'expires_at' => now()->addDay(),
        ]);

        $inviteUsedByUser = RegistrationInvite::create([
            'token' => 'invite-used-by-user',
            'created_by' => $other->id,
            'role' => 'user',
            'email' => 'someone-else@example.com',
            'expires_at' => now()->addDay(),
            'used_at' => now(),
            'used_by' => $user->id,
        ]);

        $unrelatedInvite = RegistrationInvite::create([
            'token' => 'invite-unrelated',
            'created_by' => $other->id,
            'role' => 'user',
            'email' => $other->email,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('sessions', ['id' => 'session-for-user']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'deleteme@example.com']);
        $this->assertDatabaseMissing('registration_invites', ['id' => $inviteToUser->id]);
        $this->assertDatabaseMissing('registration_invites', ['id' => $inviteUsedByUser->id]);
        $this->assertNotNull($unrelatedInvite->fresh());
    }
}
