<?php

namespace Tests\Feature\ExerciseProfiles;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileBackfillService;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Enums\SetGroupType;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseProfileBackfillTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_user_default_from_legacy_preferences_and_assigns_it_to_routines(): void
    {
        $user = User::factory()->create([
            'progression_target_default' => 8,
            'achievement_floor_default' => 6,
            'warm_up_steps_default' => [
                ['percent' => 50, 'reps' => 5],
            ],
        ]);
        $routine = Routine::factory()->withUser($user)->create();

        app(ExerciseProfileBackfillService::class)->run();

        $profile = $user->fresh()->defaultExerciseProfile;

        $this->assertNotNull($profile);
        $this->assertSame(ExerciseProfileKind::Custom, $profile->kind);
        $this->assertSame('My Custom 1', $profile->name);
        $this->assertSame(8, $profile->target_reps);
        $this->assertSame(6, $profile->floor_override);
        $this->assertSame(120, $profile->working_rest_seconds);
        $this->assertSame([['mode' => 'percent', 'percent' => 50, 'reps' => 5]], $profile->warm_up_steps);
        $this->assertSame($profile->id, $routine->fresh()->default_exercise_profile_id);
    }

    #[Test]
    public function it_creates_distinct_custom_profiles_for_distinct_legacy_block_recipes(): void
    {
        $user = User::factory()->create(['achievement_floor_default' => 4]);
        $routine = Routine::factory()->withUser($user)->create();
        $exercise = Exercise::factory()->create();

        $this->addBlock($routine, $exercise->id, targetReps: 8, workingRest: 90);
        $this->addBlock($routine, $exercise->id, targetReps: 12, workingRest: 90);

        app(ExerciseProfileBackfillService::class)->run();

        $profiles = $user->fresh()->exerciseProfiles()->orderBy('id')->get();

        $this->assertCount(3, $profiles);
        $this->assertSame(['My Custom 1', 'My Custom 2', 'My Custom 3'], $profiles->pluck('name')->all());
        $this->assertSame([6, 8, 12], $profiles->pluck('target_reps')->all());
    }

    #[Test]
    public function it_reuses_preset_target_and_floor_for_superset_exercises(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->withUser($user)->create();
        $strength = Exercise::factory()->create();
        $hypertrophy = Exercise::factory()->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'is_superset' => true,
        ]);

        foreach ([
            $strength->id => ['target_reps' => 6, 'floor' => 4, 'position' => 1],
            $hypertrophy->id => ['target_reps' => 10, 'floor' => 8, 'position' => 2],
        ] as $exerciseId => $recipe) {
            RoutineBlockExercise::create([
                'routine_block_id' => $block->id,
                'exercise_id' => $exerciseId,
                'position' => $recipe['position'],
                'working_weight_g' => 50000,
                'prescribed_reps' => $recipe['target_reps'],
                'achievement_floor_override' => $recipe['floor'],
            ]);
        }

        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 3,
            'rest_seconds' => 120,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 0,
            'rest_seconds' => 60,
        ]);

        app(ExerciseProfileBackfillService::class)->run();

        $rows = $block->fresh('blockExercises.exerciseProfile')->blockExercises->sortBy('position')->values();

        $this->assertSame('OVRLOAD Strength', $rows[0]->exerciseProfile->displayName());
        $this->assertSame('OVRLOAD Hypertrophy', $rows[1]->exerciseProfile->displayName());
        $this->assertNull($block->fresh()->shared_exercise_profile_id);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $user = User::factory()->create();

        $service = app(ExerciseProfileBackfillService::class);
        $service->run();
        $service->run();

        $this->assertSame(1, $user->fresh()->exerciseProfiles()->count());
        $this->assertSame(3, ExerciseProfile::query()->where('kind', ExerciseProfileKind::Preset)->count());
    }

    private function addBlock(Routine $routine, int $exerciseId, int $targetReps, int $workingRest): void
    {
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => $routine->blocks()->count() + 1,
            'is_superset' => false,
        ]);

        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exerciseId,
            'position' => 1,
            'working_weight_g' => 50000,
            'prescribed_reps' => $targetReps,
        ]);

        $working = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 3,
            'rest_seconds' => $workingRest,
        ]);
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 1,
            'rest_seconds' => 60,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'percent_of_working' => 50,
            'reps' => 5,
        ]);
    }
}
