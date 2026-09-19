<?php

namespace Tests\Unit\Settings\Services;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use App\Settings\Services\UserDataExporter;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDataExporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function export_includes_profile_custom_profiles_and_exercises_with_stable_keys(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'name' => 'Export Profile',
            'target_reps' => 8,
        ]);
        $primary = MuscleGroup::factory()->create(['name' => 'Chest', 'slug' => 'chest']);
        $secondary = MuscleGroup::factory()->create(['name' => 'Triceps', 'slug' => 'triceps']);
        $exercise = Exercise::factory()->custom($user)->create([
            'name' => 'Cable Fly',
            'primary_muscle_group_id' => $primary->id,
            'secondary_muscle_group_id' => $secondary->id,
        ]);

        $payload = app(UserDataExporter::class)->export($user->fresh());

        $this->assertSame([
            'exported_at',
            'profile',
            'plate_profile',
            'exercise_profiles',
            'custom_exercises',
            'routines',
            'workouts',
        ], array_keys($payload));

        $this->assertSame([
            'id',
            'name',
            'email',
            'email_verified_at',
            'weight_unit',
            'achievement_floor_default',
            'progression_target_default',
            'progression_style_default',
            'progressive_mid_block_default',
            'deload_weight_factor_default',
            'deload_reps_factor_default',
            'deload_every_n_default',
            'default_exercise_profile_id',
            'warm_up_steps_default',
            'warm_up_defaults_scope',
            'created_at',
            'updated_at',
        ], array_keys($payload['profile']));
        $this->assertNull($payload['profile']['email_verified_at']);
        $this->assertNotNull($payload['profile']['created_at']);
        $this->assertNotNull($payload['profile']['updated_at']);

        $this->assertCount(1, $payload['exercise_profiles']);
        $this->assertSame([
            'id',
            'name',
            'slug',
            'status',
            'target_reps',
            'floor_override',
            'working_rest_seconds',
            'warm_up_steps',
            'recipe_fingerprint',
        ], array_keys($payload['exercise_profiles'][0]));
        $this->assertSame($profile->id, $payload['exercise_profiles'][0]['id']);
        $this->assertSame(8, $payload['exercise_profiles'][0]['target_reps']);

        $this->assertCount(1, $payload['custom_exercises']);
        $this->assertSame($exercise->id, $payload['custom_exercises'][0]['id']);
        $this->assertSame([
            'name' => 'Chest',
            'slug' => 'chest',
        ], $payload['custom_exercises'][0]['primary_muscle_group']);
        $this->assertSame([
            'name' => 'Triceps',
            'slug' => 'triceps',
        ], $payload['custom_exercises'][0]['secondary_muscle_group']);
    }

    #[Test]
    public function export_allows_null_secondary_muscle_group(): void
    {
        $user = User::factory()->create();
        $primary = MuscleGroup::factory()->create(['name' => 'Back', 'slug' => 'back']);
        Exercise::factory()->custom($user)->create([
            'name' => 'Odd Movement',
            'primary_muscle_group_id' => $primary->id,
            'secondary_muscle_group_id' => null,
        ]);

        $payload = app(UserDataExporter::class)->export($user->fresh());

        $this->assertSame([
            'name' => 'Back',
            'slug' => 'back',
        ], $payload['custom_exercises'][0]['primary_muscle_group']);
        $this->assertNull($payload['custom_exercises'][0]['secondary_muscle_group']);
    }
}
