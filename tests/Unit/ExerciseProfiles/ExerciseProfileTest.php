<?php

namespace Tests\Unit\ExerciseProfiles;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_formats_preset_and_custom_display_names_differently(): void
    {
        $user = User::factory()->create();
        $preset = ExerciseProfile::factory()->preset()->create([
            'name' => 'Strength',
            'slug' => 'preset-strength-test',
        ]);
        $custom = ExerciseProfile::factory()->forUser($user)->create([
            'name' => 'Strength',
            'slug' => 'strength',
        ]);

        $this->assertSame('OVRLOAD Strength', $preset->displayName());
        $this->assertSame('Strength', $custom->displayName());
    }

    #[Test]
    public function only_published_profiles_are_selectable(): void
    {
        $user = User::factory()->create();
        $published = ExerciseProfile::factory()->forUser($user)->create();
        $draft = ExerciseProfile::factory()->forUser($user)->draft()->create();
        $archived = ExerciseProfile::factory()->forUser($user)->archived()->create();

        $this->assertTrue($published->isSelectable());
        $this->assertFalse($draft->isSelectable());
        $this->assertFalse($archived->isSelectable());
        $this->assertSame(ExerciseProfileKind::Custom, $published->kind);
        $this->assertSame(ExerciseProfileStatus::Draft, $draft->status);
    }

    #[Test]
    public function it_exposes_a_recipe_with_a_derived_floor(): void
    {
        $profile = ExerciseProfile::factory()->create([
            'target_reps' => 10,
            'floor_override' => null,
        ]);

        $this->assertSame(8, $profile->resolvedFloor());
        $this->assertSame(8, $profile->recipe()->resolvedFloor());
    }
}
