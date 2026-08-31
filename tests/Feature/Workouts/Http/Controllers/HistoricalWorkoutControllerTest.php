<?php

namespace Tests\Feature\Workouts\Http\Controllers;

use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Enums\SetGroupType;
use App\Shared\Enums\WarmUpWeightMode;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class HistoricalWorkoutControllerTest extends TestCase
{
    use CreatesPlayableWorkout;
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(false);
    }

    #[Test]
    public function pick_lists_routines(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create(['name' => 'Push A']);
        $this->seedPlayableRoutineBlock($routine);

        $this->actingAs($this->user)
            ->get(route('history.create.pick'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('history/PickRoutine')
                ->has('routines', 1)
                ->where('routines.0.slug', $routine->slug));
    }

    #[Test]
    public function create_form_renders_prefill(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 2, workingWeightG: 80000, prescribedReps: 6);

        $this->actingAs($this->user)
            ->get(route('history.create', $routine))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('history/Create')
                ->where('form.routine_slug', $routine->slug)
                ->has('form.blocks', 1)
                ->where('form.blocks.0.working_set_count', 2)
                ->has('form.blocks.0.working_sets', 2));
    }

    #[Test]
    public function create_form_prefills_fixed_warm_up_weight(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        [, $routineExercise] = $this->seedPlayableRoutineBlock($routine, setCount: 1, workingWeightG: 200000, prescribedReps: 5);
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $routineExercise->block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 1,
            'rest_seconds' => 45,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'weight_mode' => WarmUpWeightMode::Fixed,
            'percent_of_working' => null,
            'weight_g' => 60_000,
            'reps' => 5,
        ]);

        $this->actingAs($this->user)
            ->get(route('history.create', $routine))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('history/Create')
                ->where('form.blocks.0.warm_ups.0.weight_mode', 'fixed')
                ->where('form.blocks.0.warm_ups.0.weight_kg', 60)
                ->where('form.blocks.0.warm_ups.0.percent_of_working', null)
                ->where('form.blocks.0.warm_ups.0.reps', 5));
    }

    #[Test]
    public function store_creates_finished_workout_and_redirects_to_history(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 1, workingWeightG: 80000, prescribedReps: 5);

        $finishedAt = now()->subDay()->seconds(0)->microseconds(0);

        $response = $this->actingAs($this->user)
            ->post(route('history.store', $routine), [
                'finished_at' => $finishedAt->toDateTimeString(),
                'mode' => 'standard',
                'blocks' => [
                    [
                        'position' => 1,
                        'working_set_count' => 1,
                        'sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 3,
                                'weight_kg' => 80,
                            ],
                        ],
                    ],
                ],
            ]);

        $workout = Workout::query()->where('routine_id', $routine->id)->firstOrFail();
        $response->assertRedirect(route('history.show', $workout));
        $this->assertSame(WorkoutStatus::Finished, $workout->status);
        $this->assertTrue($workout->finished_at->equalTo($finishedAt));
        $this->assertTrue($workout->started_at->equalTo($finishedAt));

        $set = $this->firstWorkingSet($workout->id);
        $this->assertSame(3, $set->reps);
        $this->assertSame(80000, $set->weight_g);
        $this->assertNotNull($set->completed_at);
    }

    #[Test]
    public function store_logs_warm_ups_from_payload(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        [, $routineExercise] = $this->seedPlayableRoutineBlock($routine, setCount: 1, workingWeightG: 100000, prescribedReps: 5);
        $block = $routineExercise->block;
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 1,
            'rest_seconds' => 45,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'percent_of_working' => 40,
            'reps' => 5,
        ]);

        $this->actingAs($this->user)
            ->post(route('history.store', $routine), [
                'finished_at' => now()->subHour()->toDateTimeString(),
                'mode' => 'standard',
                'blocks' => [
                    [
                        'position' => 1,
                        'working_set_count' => 1,
                        'sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 5,
                                'weight_kg' => 100,
                            ],
                        ],
                        'warm_up_sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 5,
                                'weight_kg' => 40,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $workout = Workout::query()->where('routine_id', $routine->id)->firstOrFail();
        $warmUpSet = WorkoutSet::query()
            ->whereHas('setGroup', fn ($q) => $q->where('type', SetGroupType::WarmUp))
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->assertSame(5, $warmUpSet->reps);
        $this->assertSame(40000, $warmUpSet->weight_g);
        $this->assertNotNull($warmUpSet->completed_at);
    }

    #[Test]
    public function store_logs_fixed_warm_up_weight_from_payload(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        [, $routineExercise] = $this->seedPlayableRoutineBlock($routine, setCount: 1, workingWeightG: 200000, prescribedReps: 5);
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $routineExercise->block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 1,
            'rest_seconds' => 45,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'weight_mode' => WarmUpWeightMode::Fixed,
            'percent_of_working' => null,
            'weight_g' => 60_000,
            'reps' => 5,
        ]);

        $this->actingAs($this->user)
            ->post(route('history.store', $routine), [
                'finished_at' => now()->subHour()->toDateTimeString(),
                'mode' => 'standard',
                'blocks' => [
                    [
                        'position' => 1,
                        'working_set_count' => 1,
                        'sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 5,
                                'weight_kg' => 200,
                            ],
                        ],
                        'warm_up_sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 5,
                                'weight_kg' => 60,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $workout = Workout::query()->where('routine_id', $routine->id)->firstOrFail();
        $warmUpSet = WorkoutSet::query()
            ->whereHas('setGroup', fn ($q) => $q->where('type', SetGroupType::WarmUp))
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->assertSame(5, $warmUpSet->reps);
        $this->assertSame(60_000, $warmUpSet->weight_g);
        $this->assertNotNull($warmUpSet->completed_at);
    }

    #[Test]
    public function store_allows_while_in_progress_play_exists(): void
    {
        $this->createPlayableWorkout();

        $routine = Routine::factory()->withUser($this->user)->create();
        $this->seedPlayableRoutineBlock($routine);

        $this->actingAs($this->user)
            ->post(route('history.store', $routine), $this->validPayload())
            ->assertRedirect();

        $this->assertSame(2, Workout::query()->where('user_id', $this->user->id)->count());
        $this->assertNotNull(Workout::inProgressForUser($this->user));
        $this->assertSame(1, Workout::query()->where('user_id', $this->user->id)->finished()->count());
    }

    #[Test]
    public function store_deload_does_not_redirect_to_progression(): void
    {
        $this->user->update([
            'progression_target_default' => 6,
            'achievement_floor_default' => 4,
        ]);

        $routine = Routine::factory()->withUser($this->user)->create([
            'deload_weight_factor' => 0.9,
            'deload_reps_factor' => 1.0,
        ]);
        $this->seedPlayableRoutineBlock($routine, workingWeightG: 80000, prescribedReps: 6);

        $response = $this->actingAs($this->user)
            ->post(route('history.store', $routine), [
                'finished_at' => now()->subHour()->toDateTimeString(),
                'mode' => 'deload',
                'blocks' => [
                    [
                        'position' => 1,
                        'working_set_count' => 1,
                        'sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 6,
                                'weight_kg' => 72,
                            ],
                        ],
                    ],
                ],
            ]);

        $workout = Workout::query()->where('routine_id', $routine->id)->firstOrFail();
        $this->assertSame(WorkoutMode::Deload, $workout->mode);
        $response->assertRedirect(route('history.show', $workout));
    }

    #[Test]
    public function store_latest_hitting_target_redirects_to_progression(): void
    {
        $this->user->update([
            'progression_target_default' => 6,
            'achievement_floor_default' => 4,
        ]);

        $routine = Routine::factory()->withUser($this->user)->create();
        $this->seedPlayableRoutineBlock(
            $routine,
            workingWeightG: 80000,
            prescribedReps: 6,
            progressionTarget: 6,
            achievementFloor: 4,
        );

        $response = $this->actingAs($this->user)
            ->post(route('history.store', $routine), [
                'finished_at' => now()->subHour()->toDateTimeString(),
                'mode' => 'standard',
                'blocks' => [
                    [
                        'position' => 1,
                        'working_set_count' => 1,
                        'sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 6,
                                'weight_kg' => 80,
                            ],
                        ],
                    ],
                ],
            ]);

        $workout = Workout::query()->where('routine_id', $routine->id)->firstOrFail();
        $response->assertRedirect(route('workouts.progression', $workout));
    }

    #[Test]
    public function store_older_than_existing_finish_skips_progression(): void
    {
        [$existing] = $this->createFinishedWorkout(reps: 6, weightGrams: 80000);

        $routine = $existing->routine;
        $older = $existing->finished_at->copy()->subDays(2);

        $response = $this->actingAs($this->user)
            ->post(route('history.store', $routine), [
                'finished_at' => $older->toDateTimeString(),
                'mode' => 'standard',
                'blocks' => [
                    [
                        'position' => 1,
                        'working_set_count' => 1,
                        'sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 6,
                                'weight_kg' => 85,
                            ],
                        ],
                    ],
                ],
            ]);

        $historical = Workout::query()
            ->where('routine_id', $routine->id)
            ->whereKeyNot($existing->id)
            ->firstOrFail();

        $response->assertRedirect(route('history.show', $historical));
        $this->assertFalse($historical->isEligibleForProgressionReEval());
    }

    #[Test]
    public function store_can_add_set_and_skip_is_not_needed_when_block_omitted(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 1);

        $block2 = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 2,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block2->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => 40000,
            'prescribed_reps' => 10,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block2->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
            'rest_seconds' => 60,
        ]);

        $this->actingAs($this->user)
            ->post(route('history.store', $routine), [
                'finished_at' => now()->subHour()->toDateTimeString(),
                'mode' => 'standard',
                'blocks' => [
                    [
                        'position' => 1,
                        'working_set_count' => 2,
                        'sets' => [
                            [
                                'exercise_position' => 1,
                                'set_index' => 0,
                                'reps' => 6,
                                'weight_kg' => 80,
                            ],
                            [
                                'exercise_position' => 1,
                                'set_index' => 1,
                                'reps' => 5,
                                'weight_kg' => 80,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $workout = Workout::query()->where('routine_id', $routine->id)->firstOrFail();
        $this->assertCount(1, $workout->blocks);
        $this->assertSame(2, WorkoutSet::query()
            ->whereHas('setGroup', fn ($q) => $q->where('type', SetGroupType::Working))
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->count());
    }

    #[Test]
    public function store_rejects_future_finished_at(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $this->seedPlayableRoutineBlock($routine);

        $this->actingAs($this->user)
            ->from(route('history.create', $routine))
            ->post(route('history.store', $routine), [
                ...$this->validPayload(),
                'finished_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect(route('history.create', $routine))
            ->assertSessionHasErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'finished_at' => now()->subHour()->toDateTimeString(),
            'mode' => 'standard',
            'blocks' => [
                [
                    'position' => 1,
                    'working_set_count' => 1,
                    'sets' => [
                        [
                            'exercise_position' => 1,
                            'set_index' => 0,
                            'reps' => 6,
                            'weight_kg' => 80,
                        ],
                    ],
                ],
            ],
        ];
    }
}
