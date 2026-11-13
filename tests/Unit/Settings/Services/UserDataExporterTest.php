<?php

namespace Tests\Unit\Settings\Services;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Settings\Services\UserDataExporter;
use App\Users\Models\User;
use App\Workouts\Models\Workout;
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

    #[Test]
    public function export_nulls_unset_profile_enums_and_timestamps(): void
    {
        $user = User::factory()->create();
        $user->weight_unit = null;
        $user->progression_style_default = null;
        $user->progressive_mid_block_default = null;
        $user->warm_up_defaults_scope = null;
        $user->created_at = null;
        $user->updated_at = null;

        $profile = app(UserDataExporter::class)->export($user)['profile'];

        $this->assertNull($profile['weight_unit']);
        $this->assertNull($profile['progression_style_default']);
        $this->assertNull($profile['progressive_mid_block_default']);
        $this->assertNull($profile['warm_up_defaults_scope']);
        $this->assertNull($profile['created_at']);
        $this->assertNull($profile['updated_at']);
    }

    #[Test]
    public function export_lists_every_custom_profile_in_name_order(): void
    {
        $user = User::factory()->create();
        ExerciseProfile::factory()->forUser($user)->create(['name' => 'Zulu']);
        ExerciseProfile::factory()->forUser($user)->create(['name' => 'Alpha']);

        $payload = app(UserDataExporter::class)->export($user);

        $this->assertSame(
            ['Alpha', 'Zulu'],
            array_column($payload['exercise_profiles'], 'name'),
        );
    }

    #[Test]
    public function export_includes_routine_exercises_and_the_workout_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->withUser($user)->create(['name' => 'Push Day']);
        $exercise = Exercise::factory()->create([
            'name' => 'Bench Press',
            'slug' => 'bench-press',
        ]);
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'prescribed_reps' => 5,
        ]);
        Workout::factory()->create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
            'started_at' => now(),
        ]);

        $payload = app(UserDataExporter::class)->export($user);

        $this->assertSame([
            'id' => $exercise->id,
            'name' => 'Bench Press',
            'slug' => 'bench-press',
        ], $payload['routines'][0]['blocks'][0]['block_exercises'][0]['exercise']);
        $this->assertSame([
            'id' => $routine->id,
            'name' => 'Push Day',
            'slug' => $routine->slug,
        ], $payload['workouts'][0]['routine']);
    }
}
