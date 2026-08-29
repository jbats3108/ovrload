<?php

namespace Tests\Feature\Settings;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDataExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_cannot_download_data_export(): void
    {
        $this->get(route('profile.data-export'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_can_download_json_export_of_their_data_only(): void
    {
        $user = User::factory()->create([
            'name' => 'Export Owner',
            'email' => 'owner@example.com',
        ]);
        $other = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
        ]);

        $ownExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'name' => 'Owner Custom Lift',
            'slug' => 'owner-custom-lift',
        ]);
        Exercise::factory()->create([
            'user_id' => $other->id,
            'name' => 'Other Custom Lift',
            'slug' => 'other-custom-lift',
        ]);

        $ownRoutine = Routine::factory()->create([
            'user_id' => $user->id,
            'name' => 'Owner Routine',
        ]);
        $ownProfile = ExerciseProfile::factory()->forUser($user)->create([
            'name' => 'Owner Profile',
        ]);
        ExerciseProfile::factory()->forUser($other)->create([
            'name' => 'Other Profile',
        ]);
        Routine::factory()->create([
            'user_id' => $other->id,
            'name' => 'Other Routine',
        ]);

        $response = $this->actingAs($user)
            ->get(route('profile.data-export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $this->assertStringContainsString(
            'attachment; filename=ovrload-export-'.$user->id,
            (string) $response->headers->get('content-disposition'),
        );

        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($user->id, $payload['profile']['id']);
        $this->assertSame('Export Owner', $payload['profile']['name']);
        $this->assertSame('owner@example.com', $payload['profile']['email']);
        $this->assertArrayNotHasKey('password', $payload['profile']);

        $this->assertArrayHasKey('plate_profile', $payload);
        $this->assertContains('Owner Profile', array_column($payload['exercise_profiles'], 'name'));
        $this->assertNotContains('Other Profile', array_column($payload['exercise_profiles'], 'name'));
        $this->assertContains($ownProfile->id, array_column($payload['exercise_profiles'], 'id'));
        $this->assertArrayHasKey('exported_at', $payload);

        $customNames = array_column($payload['custom_exercises'], 'name');
        $this->assertContains('Owner Custom Lift', $customNames);
        $this->assertNotContains('Other Custom Lift', $customNames);
        $this->assertContains($ownExercise->id, array_column($payload['custom_exercises'], 'id'));

        $routineNames = array_column($payload['routines'], 'name');
        $this->assertContains('Owner Routine', $routineNames);
        $this->assertNotContains('Other Routine', $routineNames);
        $this->assertContains($ownRoutine->id, array_column($payload['routines'], 'id'));

        $this->assertIsArray($payload['workouts']);
    }

    #[Test]
    public function profile_settings_page_loads_with_data_export_route_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/Profile'));

        $this->assertSame(
            url('/settings/data-export'),
            route('profile.data-export'),
        );
    }
}
