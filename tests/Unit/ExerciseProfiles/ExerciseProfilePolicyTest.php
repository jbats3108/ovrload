<?php

namespace Tests\Unit\ExerciseProfiles;

use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Policies\ExerciseProfilePolicy;
use App\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseProfilePolicyTest extends TestCase
{
    use RefreshDatabase;

    private ExerciseProfilePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->policy = new ExerciseProfilePolicy;
    }

    #[Test]
    public function an_admin_can_update_a_draft_preset(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $profile = ExerciseProfile::factory()->preset()->draft()->create();

        $this->assertTrue($this->policy->update($admin, $profile));
    }

    #[Test]
    public function an_admin_cannot_update_a_published_preset(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $profile = ExerciseProfile::factory()->preset()->create();

        $this->assertFalse($this->policy->update($admin, $profile));
    }

    #[Test]
    public function a_non_admin_cannot_update_a_draft_preset(): void
    {
        $user = User::factory()->withRole('user')->create();
        $profile = ExerciseProfile::factory()->preset()->draft()->create();

        $this->assertFalse($this->policy->update($user, $profile));
    }

    #[Test]
    public function the_owner_can_update_their_published_custom_profile(): void
    {
        $user = User::factory()->withRole('user')->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->update($user, $profile));
    }

    #[Test]
    public function another_user_cannot_update_a_custom_profile(): void
    {
        $owner = User::factory()->withRole('user')->create();
        $other = User::factory()->withRole('user')->create();
        $profile = ExerciseProfile::factory()->forUser($owner)->create();

        $this->assertFalse($this->policy->update($other, $profile));
    }

    #[Test]
    public function the_owner_cannot_update_an_archived_custom_profile(): void
    {
        $user = User::factory()->withRole('user')->create();
        $profile = ExerciseProfile::factory()->forUser($user)->archived()->create();

        $this->assertFalse($this->policy->update($user, $profile));
    }

    #[Test]
    public function the_owner_cannot_update_a_custom_profile_that_is_not_published(): void
    {
        $user = User::factory()->withRole('user')->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'status' => ExerciseProfileStatus::Draft,
            'published_at' => null,
        ]);

        $this->assertFalse($this->policy->update($user, $profile));
    }

    #[Test]
    public function an_admin_cannot_update_another_users_custom_profile(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('user')->create();
        $profile = ExerciseProfile::factory()->forUser($owner)->create();

        $this->assertFalse($this->policy->update($admin, $profile));
    }
}
