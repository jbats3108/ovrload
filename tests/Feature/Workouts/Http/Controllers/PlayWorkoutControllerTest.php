<?php

namespace Tests\Feature\Workouts\Http\Controllers;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutService;
use Database\Seeders\ExerciseProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class PlayWorkoutControllerTest extends TestCase
{
    use CreatesPlayableWorkout;
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(false);
        $this->seed(ExerciseProfileSeeder::class);
    }

    #[Test]
    public function it_renders_the_player_for_the_owner(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('workouts/Play')
                ->where('workout.id', $workout->ulid)
                ->where('workout.routine_name', $workout->routine->getName())
                ->has('workout.blocks', 1)
                ->where('workout.blocks.0.exercises.0.achievement_floor', $this->user->achievement_floor_default)
                ->has('plate_profile.bars')
                ->has('plate_profile.plates')
            );
    }

    #[Test]
    public function it_snapshots_bump_threshold_from_prescribed_reps(): void
    {
        $this->user->update([
            'achievement_floor_default' => 4,
            'progression_target_default' => 99,
        ]);
        $workout = $this->createPlayableWorkout();
        $prescribed = $workout->blocks->first()->blockExercises->first()->prescribed_reps;

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('workouts/Play')
                ->where('workout.blocks.0.exercises.0.achievement_floor', 4)
                ->where('workout.blocks.0.exercises.0.progression_target', $prescribed)
                ->where('workout.blocks.0.exercises.0.prescribed_reps', $prescribed)
            );
    }

    #[Test]
    public function it_uses_a_profile_derived_floor_in_the_workout_snapshot(): void
    {
        $profile = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();
        $this->user->update(['achievement_floor_default' => 1]);
        $routine = Routine::factory()->withUser($this->user)->create([
            'default_exercise_profile_id' => $profile->id,
        ]);
        $exercise = Exercise::factory()->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'shared_exercise_profile_id' => $profile->id,
            'shared_profile_fingerprint' => $profile->recipe()->sharedFingerprint(),
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 60000,
            'prescribed_reps' => 6,
            'exercise_profile_id' => $profile->id,
            'exercise_profile_fingerprint' => $profile->recipe()->fingerprint(),
            'floor_is_derived' => true,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
            'rest_seconds' => 180,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 0,
            'rest_seconds' => 60,
        ]);

        $workout = app(WorkoutService::class)->createWorkout($routine);

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('workout.blocks.0.exercises.0.achievement_floor', 4));
    }

    #[Test]
    public function it_soft_fails_other_users(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->secondUser)
            ->get(route('workouts.play', $workout))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that workout.');
    }

    #[Test]
    public function it_completes_a_set_and_finishes_the_workout(): void
    {
        $workout = $this->createPlayableWorkout();
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('workouts.sets.complete', ['workout' => $workout, 'set' => $set]), [
                'reps' => 5,
                'weight_kg' => 80,
            ])
            ->assertRedirect();

        $set->refresh();
        $this->assertSame(5, $set->reps);
        $this->assertSame(80000, $set->weight_g);
        $this->assertNotNull($set->completed_at);

        $this->actingAs($this->user)
            ->post(route('workouts.finish', $workout))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(WorkoutStatus::Finished, $workout->fresh()->status);
    }

    #[Test]
    public function it_persists_a_logged_plate_stack_for_resume(): void
    {
        $workout = $this->createPlayableWorkout();
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('workouts.sets.complete', ['workout' => $workout, 'set' => $set]), [
                'reps' => 5,
                'weight_kg' => 80,
                'plate_stack' => [
                    'bar_g' => 20000,
                    'per_side' => [
                        ['denomination_g' => 20000, 'count' => 1],
                        ['denomination_g' => 10000, 'count' => 1],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame([
            'bar_g' => 20000,
            'per_side' => [
                ['denomination_g' => 20000, 'count' => 1],
                ['denomination_g' => 10000, 'count' => 1],
            ],
        ], $set->fresh()->plate_stack);

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('workout.blocks.0.sets.0.plate_stack.bar_g', 20000)
                ->where('workout.blocks.0.sets.0.plate_stack.per_side.0.denomination_g', 20000)
            );
    }

    #[Test]
    public function it_rejects_recompleting_an_already_logged_set(): void
    {
        $workout = $this->createPlayableWorkout();
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('workouts.sets.complete', ['workout' => $workout, 'set' => $set]), [
                'reps' => 5,
                'weight_kg' => 80,
            ])
            ->assertRedirect();

        $completedAt = $set->fresh()->completed_at;

        $this->actingAs($this->user)
            ->post(route('workouts.sets.complete', ['workout' => $workout, 'set' => $set]), [
                'reps' => 8,
                'weight_kg' => 100,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('set');

        $set->refresh();
        $this->assertSame(5, $set->reps);
        $this->assertSame(80000, $set->weight_g);
        $this->assertEquals($completedAt?->timestamp, $set->completed_at?->timestamp);
    }

    #[Test]
    public function it_accepts_fractional_kilogram_weights(): void
    {
        $workout = $this->createPlayableWorkout();
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('workouts.sets.complete', ['workout' => $workout, 'set' => $set]), [
                'reps' => 8,
                'weight_kg' => 28.75,
            ])
            ->assertRedirect();

        $set->refresh();
        $this->assertSame(28750, $set->weight_g);
        $this->assertNotNull($set->completed_at);
    }

    #[Test]
    public function it_snapshots_setup_after_warm_up_into_the_player(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'has_setup_after_warm_up' => true,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => 80000,
            'prescribed_reps' => 6,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 1,
            'rest_seconds' => 45,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
            'rest_seconds' => 90,
        ]);

        $workout = app(WorkoutService::class)->createWorkout($routine);

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('workouts/Play')
                ->where('workout.blocks.0.has_setup_after_warm_up', true)
            );
    }

    #[Test]
    public function it_snapshots_setup_after_on_warm_up_steps_into_the_player(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => 80000,
            'prescribed_reps' => 6,
        ]);
        $warmUpGroup = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 2,
            'rest_seconds' => 45,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUpGroup->id,
            'position' => 1,
            'percent_of_working' => 40,
            'reps' => 5,
            'has_setup_after' => true,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUpGroup->id,
            'position' => 2,
            'percent_of_working' => 60,
            'reps' => 3,
            'has_setup_after' => false,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
            'rest_seconds' => 90,
        ]);

        $workout = app(WorkoutService::class)->createWorkout($routine);

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('workouts/Play')
                ->where('workout.blocks.0.sets.0.has_setup_after', true)
                ->where('workout.blocks.0.sets.1.has_setup_after', false)
            );
    }

    #[Test]
    public function it_snapshots_exercise_equipment_into_the_player(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->dumbbell()->create([
                'name' => 'Alternate Hammer Curl',
            ])->id,
            'position' => 1,
            'working_weight_g' => 20000,
            'prescribed_reps' => 10,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
            'rest_seconds' => 90,
        ]);

        $workout = app(WorkoutService::class)->createWorkout($routine);

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('workouts/Play')
                ->where('workout.blocks.0.sets.0.equipment', 'dumbbell')
                ->where('workout.blocks.0.exercises.0.equipment', 'dumbbell')
            );
    }

    #[Test]
    public function it_snapshots_dropsets_into_the_player_and_completes_them(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => 20000,
            'prescribed_reps' => 12,
        ]);
        $working = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
            'rest_seconds' => 90,
        ]);
        RoutineDropsetSegment::create([
            'routine_set_group_id' => $working->id,
            'set_index' => 0,
            'position' => 1,
            'weight_g' => 20000,
        ]);
        RoutineDropsetSegment::create([
            'routine_set_group_id' => $working->id,
            'set_index' => 0,
            'position' => 2,
            'weight_g' => 15000,
        ]);

        $workout = app(WorkoutService::class)->createWorkout($routine);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('workouts.play', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('workouts/Play')
                ->where('workout.blocks.0.sets.0.is_dropset', true)
                ->where('workout.blocks.0.sets.0.segments.0.weight_kg', 20)
                ->where('workout.blocks.0.sets.0.segments.1.weight_kg', 15)
            );

        $this->actingAs($this->user)
            ->post(route('workouts.sets.complete', ['workout' => $workout, 'set' => $set]), [
                'reps' => 10,
                'segments' => [
                    ['weight_kg' => 18],
                    ['weight_kg' => 14],
                    ['weight_kg' => 10],
                ],
            ])
            ->assertRedirect();

        $set->refresh()->load('segments');
        $this->assertSame(10, $set->reps);
        $this->assertNull($set->weight_g);
        $this->assertSame([18000, 14000, 10000], $set->segments->pluck('weight_g')->all());
    }

    #[Test]
    public function it_promotes_a_set_to_dropset_via_http(): void
    {
        $workout = $this->createPlayableWorkout();
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('workouts.sets.promote-dropset', ['workout' => $workout, 'set' => $set]), [
                'segments' => [
                    ['weight_kg' => 80],
                    ['weight_kg' => 60],
                ],
            ])
            ->assertRedirect();

        $set->refresh()->load('segments');
        $this->assertTrue($set->isDropset());
        $this->assertSame([80000, 60000], $set->segments->pluck('weight_g')->all());
    }

    #[Test]
    public function it_demotes_a_dropset_via_http(): void
    {
        $workout = $this->createPlayableWorkout();
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('workouts.sets.promote-dropset', ['workout' => $workout, 'set' => $set]), [
                'segments' => [
                    ['weight_kg' => 80],
                    ['weight_kg' => 60],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('workouts.sets.demote-dropset', ['workout' => $workout, 'set' => $set]))
            ->assertRedirect();

        $set->refresh()->load('segments');
        $this->assertFalse($set->isDropset());
        $this->assertCount(0, $set->segments);
    }
}
