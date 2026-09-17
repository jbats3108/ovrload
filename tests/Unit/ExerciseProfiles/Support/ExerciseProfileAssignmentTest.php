<?php

namespace Tests\Unit\ExerciseProfiles\Support;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Support\ExerciseProfileAssignment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseProfileAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function shared_profile_fingerprint_prefers_incoming_over_recipe(): void
    {
        $profile = ExerciseProfile::factory()->create();
        $computed = $profile->recipe()->sharedFingerprint();

        $this->assertSame(
            'incoming-fingerprint',
            ExerciseProfileAssignment::sharedProfileFingerprint($profile, 'incoming-fingerprint'),
        );
        $this->assertSame(
            $computed,
            ExerciseProfileAssignment::sharedProfileFingerprint($profile, null),
        );
        $this->assertNotSame(
            $computed,
            ExerciseProfileAssignment::sharedProfileFingerprint($profile, 'incoming-fingerprint'),
        );
    }

    #[Test]
    public function shared_profile_fingerprint_is_null_when_profile_is_missing(): void
    {
        $this->assertNull(ExerciseProfileAssignment::sharedProfileFingerprint(null, 'ignored'));
    }
}
