<?php

namespace Tests\Feature\Workouts;

use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\TestCase;

class WorkoutServiceTest extends TestCase
{
    use CreatesPlayableWorkout;
    use RefreshDatabase;

    private WorkoutService $workoutService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workoutService = app(WorkoutService::class);
    }

    #[Test]
    public function it_throw_an_exception_if_it_tries_to_create_a_workout_from_a_routine_with_no_exercises(): void
    {
        // Given
        $routine = Routine::factory()->create();

        // When
        try {
            $this->workoutService->createWorkout($routine);
        } catch (WorkoutServiceException $workoutServiceException) {
            // Then
            $this->assertSame('Unable to create a workout for a routine with no exercises',
                $workoutServiceException->getMessage());

            return;
        }

        $this->fail();
    }

    #[Test]
    public function it_creates_a_workout_from_a_routine(): void
    {
        // Given
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, restSeconds: null);

        // When
        $workout = $this->workoutService->createWorkout($routine);

        // Then
        $this->assertTrue($workout->routine->is($routine));
        $this->assertSame('in_progress', $workout->status->value);
        $this->assertSame('standard', $workout->mode->value);
    }

    #[Test]
    public function it_creates_workout_blocks_for_each_routine_block(): void
    {
        // Given
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, restSeconds: null);

        // When
        $workout = $this->workoutService->createWorkout($routine);

        // Then
        $this->assertCount(1, $workout->blocks);
    }

    #[Test]
    public function it_creates_the_right_number_of_sets_for_a_block_exercise(): void
    {
        // Given
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 4);

        // When
        $workout = $this->workoutService->createWorkout($routine);

        // Then
        $workoutBlockExercise = $workout->blocks->first()->blockExercises->first();
        $sets = WorkoutSet::where('workout_block_exercise_id', $workoutBlockExercise->id)->get();

        $this->assertCount(4, $sets);
        $sets->each(fn (WorkoutSet $workoutSet, int $key) => $this->assertSame($key, $workoutSet->set_index));
    }

    #[Test]
    public function it_applies_deload_factors_when_starting_in_deload_mode(): void
    {
        $routine = Routine::factory()->create([
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 0.5,
        ]);
        $this->seedPlayableRoutineBlock($routine, setCount: 1);

        $workout = $this->workoutService->createWorkout($routine, WorkoutMode::Deload);

        $exercise = $workout->blocks->first()->blockExercises->first();

        $this->assertSame('deload', $workout->mode->value);
        $this->assertSame(40000, $exercise->working_weight_g);
        $this->assertSame(3, $exercise->prescribed_reps);
    }

    #[Test]
    public function it_omits_warm_ups_when_starting_in_deload_mode(): void
    {
        $routine = Routine::factory()->create([
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 1,
        ]);
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
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 2,
            'rest_seconds' => 45,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'percent_of_working' => 40,
            'reps' => 5,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 2,
            'percent_of_working' => 60,
            'reps' => 3,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 2,
            'rest_seconds' => 90,
        ]);

        $workout = $this->workoutService->createWorkout($routine, WorkoutMode::Deload);
        $workoutBlock = $workout->blocks->first()->load('setGroups');

        $this->assertFalse($workoutBlock->has_setup_after_warm_up);
        $this->assertNull($workoutBlock->setGroups->first(
            fn ($group): bool => $group->type === SetGroupType::WarmUp
        ));
        $this->assertCount(1, $workoutBlock->setGroups);
        $this->assertSame(SetGroupType::Working, $workoutBlock->setGroups->first()->type);
    }

    #[Test]
    public function it_rejects_a_second_in_progress_workout_for_the_same_user(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, restSeconds: null);
        $this->workoutService->createWorkout($routine);

        $other = Routine::factory()->create(['user_id' => $routine->user_id]);
        $this->seedPlayableRoutineBlock($other, restSeconds: null);

        $this->expectException(WorkoutServiceException::class);
        $this->expectExceptionMessage(WorkoutService::ALREADY_IN_PROGRESS_ERROR);

        $this->workoutService->createWorkout($other);
    }

    #[Test]
    public function unique_index_rejects_a_second_in_progress_row_for_the_same_user(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, restSeconds: null);
        $this->workoutService->createWorkout($routine);

        $this->expectException(UniqueConstraintViolationException::class);

        Workout::query()->create([
            'user_id' => $routine->user_id,
            'routine_id' => $routine->id,
            'mode' => WorkoutMode::Standard,
            'status' => WorkoutStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    #[Test]
    public function it_adds_a_working_set_round_to_the_block(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 2);
        $workout = $this->workoutService->createWorkout($routine);
        $block = $workout->blocks->first();

        $this->workoutService->addWorkingSet($block);

        $working = $block->fresh()->workingSetGroup;
        $this->assertSame(3, $working->set_count);
        $this->assertCount(3, $working->sets);
    }

    #[Test]
    public function it_removes_an_incomplete_working_set_round(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 2);
        $workout = $this->workoutService->createWorkout($routine);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->where('set_index', 1)
            ->firstOrFail();

        $this->workoutService->removeWorkingSetRound($set);

        $working = $workout->blocks->first()->fresh()->workingSetGroup;
        $this->assertSame(1, $working->set_count);
        $this->assertCount(1, $working->sets);
    }

    #[Test]
    public function it_reindexes_remaining_sets_after_removing_an_earlier_round(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 3);
        $workout = $this->workoutService->createWorkout($routine);
        $first = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->where('set_index', 0)
            ->firstOrFail();

        $this->workoutService->removeWorkingSetRound($first);

        $indexes = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->orderBy('set_index')
            ->pluck('set_index')
            ->all();

        $this->assertSame([0, 1], $indexes);
        $this->assertSame(2, $workout->blocks->first()->fresh()->workingSetGroup->set_count);
    }

    #[Test]
    public function it_does_not_remove_the_last_working_set(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 1);
        $workout = $this->workoutService->createWorkout($routine);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->expectException(WorkoutServiceException::class);
        $this->expectExceptionMessage(WorkoutService::CANNOT_REMOVE_LAST_WORKING_SET_ERROR);

        $this->workoutService->removeWorkingSetRound($set);
    }

    #[Test]
    public function it_skips_rest_of_block_keeping_logged_sets(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 3);
        $workout = $this->workoutService->createWorkout($routine);
        $block = $workout->blocks->first();
        $first = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->where('set_index', 0)
            ->firstOrFail();
        $this->workoutService->completeSet($first, reps: 5, weightGrams: 80000);

        $this->workoutService->skipRestOfBlock($block->fresh(['setGroups.sets', 'workout']));

        $working = $block->fresh()->workingSetGroup;
        $this->assertSame(1, $working->set_count);
        $this->assertCount(1, $working->sets);
        $this->assertNotNull($working->sets->first()->completed_at);
    }

    #[Test]
    public function it_skips_rest_of_block_clearing_warm_ups_and_all_working(): void
    {
        $routine = Routine::factory()->create();
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
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 2,
            'rest_seconds' => 45,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'percent_of_working' => 40,
            'reps' => 5,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 2,
            'percent_of_working' => 60,
            'reps' => 3,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 3,
            'rest_seconds' => 90,
        ]);

        $workout = $this->workoutService->createWorkout($routine);
        $workoutBlock = $workout->blocks->first();

        $this->workoutService->skipRestOfBlock($workoutBlock->fresh(['setGroups.sets', 'workout']));

        $workoutBlock->refresh()->load('setGroups.sets');
        $this->assertSame(0, $workoutBlock->warmUpSetGroup->set_count);
        $this->assertCount(0, $workoutBlock->warmUpSetGroup->sets);
        $this->assertSame(0, $workoutBlock->workingSetGroup->set_count);
        $this->assertCount(0, $workoutBlock->workingSetGroup->sets);
    }

    #[Test]
    public function it_skips_incomplete_superset_leg_and_later_rounds(): void
    {
        $routine = Routine::factory()->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'is_superset' => true,
        ]);
        $exerciseA = RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => 80000,
            'prescribed_reps' => 6,
        ]);
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 2,
            'working_weight_g' => 40000,
            'prescribed_reps' => 8,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 2,
            'rest_seconds' => 90,
        ]);

        $workout = $this->workoutService->createWorkout($routine);
        $workoutBlock = $workout->blocks->first();
        $setA = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('id', $workoutBlock->id))
            ->where('set_index', 0)
            ->whereHas('blockExercise', fn ($q) => $q->where('position', 1))
            ->firstOrFail();
        $this->assertSame($exerciseA->exercise_id, $setA->blockExercise->exercise_id);
        $this->workoutService->completeSet($setA, reps: 6, weightGrams: 80000);

        $this->workoutService->skipRestOfBlock($workoutBlock->fresh(['setGroups.sets', 'workout']));

        $working = $workoutBlock->fresh()->workingSetGroup->load('sets.blockExercise');
        $this->assertSame(1, $working->set_count);
        $this->assertCount(1, $working->sets);
        $this->assertSame(1, $working->sets->first()->blockExercise->position);
        $this->assertNotNull($working->sets->first()->completed_at);
    }

    #[Test]
    public function it_rejects_skip_rest_when_nothing_incomplete(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 1);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstWorkingSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);

        $this->expectException(WorkoutServiceException::class);
        $this->expectExceptionMessage(WorkoutService::NOTHING_TO_SKIP_IN_BLOCK_ERROR);

        $this->workoutService->skipRestOfBlock($workout->blocks->first()->fresh(['setGroups.sets', 'workout']));
    }

    #[Test]
    public function it_rejects_skip_rest_when_workout_not_in_progress(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 2);
        $workout = $this->workoutService->createWorkout($routine);
        $this->workoutService->finishWorkout($workout);

        $this->expectException(WorkoutServiceException::class);
        $this->expectExceptionMessage(WorkoutService::WORKOUT_NOT_IN_PROGRESS_ERROR);

        $this->workoutService->skipRestOfBlock($workout->blocks->first()->fresh(['setGroups.sets', 'workout']));
    }

    #[Test]
    public function it_discards_an_in_progress_workout(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, restSeconds: null);
        $workout = $this->workoutService->createWorkout($routine);

        $this->workoutService->discardWorkout($workout);

        $this->assertSame(WorkoutStatus::Discarded, $workout->fresh()->status);
        $this->assertNull($workout->fresh()->finished_at);
    }

    #[Test]
    public function it_rejects_finishing_a_workout_that_is_no_longer_in_progress(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, restSeconds: null);
        $workout = $this->workoutService->createWorkout($routine);
        $this->workoutService->finishWorkout($workout);

        $this->expectException(WorkoutServiceException::class);
        $this->expectExceptionMessage(WorkoutService::WORKOUT_NOT_IN_PROGRESS_ERROR);

        $this->workoutService->finishWorkout($workout->fresh());
    }

    #[Test]
    public function it_rejects_discarding_a_finished_workout(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, restSeconds: null);
        $workout = $this->workoutService->createWorkout($routine);
        $this->workoutService->finishWorkout($workout);

        $this->expectException(WorkoutServiceException::class);
        $this->expectExceptionMessage(WorkoutService::WORKOUT_NOT_IN_PROGRESS_ERROR);

        $this->workoutService->discardWorkout($workout->fresh());
    }

    #[Test]
    public function it_snapshots_dropset_segments_and_scales_them_on_deload(): void
    {
        $routine = Routine::factory()->create([
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 1,
        ]);
        [$working] = $this->seedPlayableRoutineBlock($routine, setCount: 1, restSeconds: null);
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
        RoutineDropsetSegment::create([
            'routine_set_group_id' => $working->id,
            'set_index' => 0,
            'position' => 3,
            'weight_g' => 10000,
        ]);

        $workout = $this->workoutService->createWorkout($routine, WorkoutMode::Deload);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->with('segments')
            ->firstOrFail();

        $this->assertTrue($set->isDropset());
        $this->assertSame([10000, 7500, 5000], $set->segments->pluck('weight_g')->all());
    }

    #[Test]
    public function it_uses_deload_alternate_exercise_and_weight_on_deload_start(): void
    {
        $routine = Routine::factory()->create([
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 0.5,
        ]);
        [, $routineExercise] = $this->seedPlayableRoutineBlock($routine, setCount: 1, restSeconds: null);
        $alternate = Exercise::factory()->create(['name' => 'Goblet Squat']);
        $routineExercise->update([
            'deload_exercise_id' => $alternate->id,
            'deload_working_weight_g' => 40000,
        ]);

        $workout = $this->workoutService->createWorkout($routine, WorkoutMode::Deload);
        $exercise = $workout->blocks->first()->blockExercises->first();

        $this->assertSame($alternate->id, $exercise->exercise_id);
        $this->assertSame('Goblet Squat', $exercise->exercise_name);
        $this->assertSame(40000, $exercise->working_weight_g);
        $this->assertSame(3, $exercise->prescribed_reps);
    }

    #[Test]
    public function it_ignores_deload_alternate_on_standard_start(): void
    {
        $routine = Routine::factory()->create();
        [, $routineExercise] = $this->seedPlayableRoutineBlock($routine, setCount: 1, restSeconds: null);
        $primaryId = $routineExercise->exercise_id;
        $alternate = Exercise::factory()->create();
        $routineExercise->update([
            'deload_exercise_id' => $alternate->id,
            'deload_working_weight_g' => 40000,
        ]);

        $workout = $this->workoutService->createWorkout($routine, WorkoutMode::Standard);
        $exercise = $workout->blocks->first()->blockExercises->first();

        $this->assertSame($primaryId, $exercise->exercise_id);
        $this->assertSame(80000, $exercise->working_weight_g);
    }

    #[Test]
    public function it_skips_dropset_segments_on_deload_when_alternate_is_set(): void
    {
        $routine = Routine::factory()->create([
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 1,
        ]);
        [$working, $routineExercise] = $this->seedPlayableRoutineBlock($routine, setCount: 1, restSeconds: null);
        $routineExercise->update([
            'deload_exercise_id' => Exercise::factory()->create()->id,
            'deload_working_weight_g' => 40000,
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

        $workout = $this->workoutService->createWorkout($routine, WorkoutMode::Deload);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->with('segments')
            ->firstOrFail();

        $this->assertFalse($set->isDropset());
        $this->assertCount(0, $set->segments);
    }

    #[Test]
    public function it_completes_a_dropset_with_segments(): void
    {
        $routine = Routine::factory()->create();
        [$working] = $this->seedPlayableRoutineBlock($routine, setCount: 1, restSeconds: null);
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

        $workout = $this->workoutService->createWorkout($routine);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->workoutService->completeSet($set, reps: 12, weightGrams: null, segmentWeightGrams: [18000, 14000, 10000]);

        $set->refresh()->load('segments');
        $this->assertSame(12, $set->reps);
        $this->assertNull($set->weight_g);
        $this->assertNotNull($set->completed_at);
        $this->assertSame([18000, 14000, 10000], $set->segments->pluck('weight_g')->all());
    }

    #[Test]
    public function it_promotes_a_single_set_to_dropset(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 1);
        $workout = $this->workoutService->createWorkout($routine);
        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();

        $this->assertFalse($set->isDropset());

        $this->workoutService->promoteToDropset($set, [20000, 15000]);

        $set->refresh()->load('segments');
        $this->assertTrue($set->isDropset());
        $this->assertSame([20000, 15000], $set->segments->pluck('weight_g')->all());
    }
}
