<?php

namespace Tests\Feature\Workouts;

use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Shared\Enums\SetGroupType;
use App\Users\Enums\BumpWhen;
use App\Users\Models\User;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutProgressionService;
use App\Workouts\Services\WorkoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutService $workoutService;

    private WorkoutProgressionService $progressionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workoutService = app(WorkoutService::class);
        $this->progressionService = app(WorkoutProgressionService::class);
    }

    #[Test]
    public function finish_carries_forward_highest_achieved_weight(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);

        $this->workoutService->completeSet($set, reps: 5, weightGrams: 85000);
        $this->workoutService->finishWorkout($workout);

        $this->assertSame(85000, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function finish_offers_bump_when_prescribed_reps_are_hit(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);

        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(1, $bumps);
        $this->assertSame($routineExercise->id, $bumps->first()->routineBlockExerciseId);
        $this->assertSame(80000, $bumps->first()->fromWeightG);
        $this->assertSame(82500, $bumps->first()->toWeightG);
    }

    #[Test]
    public function finish_ignores_work_logged_in_an_ad_hoc_block_for_progression(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $exercise = Exercise::factory()->shared()->create();
        $adHocBlock = $this->workoutService->addAdHocExercise($workout, $exercise->id);
        $workingGroup = $adHocBlock->setGroups->firstWhere('type', SetGroupType::Working);
        $adHocSet = $workingGroup->sets->first();

        $this->workoutService->completeSet($adHocSet, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
        $this->assertSame(80000, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function finish_does_not_offer_bump_when_only_floor_reps_are_hit(): void
    {
        [$routine] = $this->seedRoutine(workingWeightG: 20000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);

        $this->workoutService->completeSet($set, reps: 4, weightGrams: 20000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
    }

    #[Test]
    public function last_at_top_weight_skips_bump_when_top_set_misses_target(): void
    {
        [$routine] = $this->seedRoutine(
            workingWeightG: 20000,
            prescribedReps: 6,
            achievementFloor: 4,
            bumpWhen: BumpWhen::LastAtTopWeight,
            setCount: 3,
        );
        $workout = $this->workoutService->createWorkout($routine);
        $this->assertSame(BumpWhen::LastAtTopWeight, $workout->bump_when);

        $sets = $this->workingSets($workout->id);
        $this->workoutService->completeSet($sets[0], reps: 6, weightGrams: 20000);
        $this->workoutService->completeSet($sets[1], reps: 6, weightGrams: 22500);
        $this->workoutService->completeSet($sets[2], reps: 4, weightGrams: 25000);

        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
    }

    #[Test]
    public function last_at_top_weight_offers_bump_from_last_heaviest_set(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(
            workingWeightG: 20000,
            prescribedReps: 6,
            achievementFloor: 4,
            bumpWhen: BumpWhen::LastAtTopWeight,
            setCount: 3,
        );
        $workout = $this->workoutService->createWorkout($routine);

        $sets = $this->workingSets($workout->id);
        $this->workoutService->completeSet($sets[0], reps: 6, weightGrams: 20000);
        $this->workoutService->completeSet($sets[1], reps: 6, weightGrams: 20000);
        $this->workoutService->completeSet($sets[2], reps: 4, weightGrams: 15000);

        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(1, $bumps);
        $this->assertSame($routineExercise->id, $bumps->first()->routineBlockExerciseId);
    }

    #[Test]
    public function any_set_still_offers_bump_when_earlier_set_hit_target(): void
    {
        [$routine] = $this->seedRoutine(
            workingWeightG: 20000,
            prescribedReps: 6,
            achievementFloor: 4,
            bumpWhen: BumpWhen::AnySet,
            setCount: 3,
        );
        $workout = $this->workoutService->createWorkout($routine);

        $sets = $this->workingSets($workout->id);
        $this->workoutService->completeSet($sets[0], reps: 6, weightGrams: 20000);
        $this->workoutService->completeSet($sets[1], reps: 6, weightGrams: 22500);
        $this->workoutService->completeSet($sets[2], reps: 4, weightGrams: 25000);

        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(1, $bumps);
    }

    #[Test]
    public function deload_finish_skips_carry_forward_and_bumps(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 3, achievementFloor: 1);
        $routine->update(['deload_weight_factor' => 0.5, 'deload_reps_factor' => 1]);
        $workout = $this->workoutService->createWorkout($routine, WorkoutMode::Deload);
        $set = $this->firstSet($workout->id);

        $this->workoutService->completeSet($set, reps: 6, weightGrams: 50000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
        $this->assertSame(80000, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function confirmed_bumps_update_routine_working_weights(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $this->assertSame(82500, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function confirmed_bumps_create_bump_records(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $this->assertDatabaseHas('bump_records', [
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $routineExercise->id,
            'from_weight_g' => 80000,
            'to_weight_g' => 82500,
            'undone_at' => null,
        ]);
    }

    #[Test]
    public function confirmed_undos_revert_bump_and_mark_record_undone(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);
        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $recordId = $workout->fresh()->bumpRecords->first()->id;
        $this->progressionService->applyConfirmedUndos($workout, [$recordId]);

        $this->assertSame(80000, $routineExercise->fresh()->working_weight_g);
        $this->assertNotNull($workout->fresh()->bumpRecords->first()->undone_at);
    }

    #[Test]
    public function confirmed_bumps_skip_when_routine_weight_already_changed(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $routineExercise->update(['working_weight_g' => 100000]);

        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $this->assertSame(100000, $routineExercise->fresh()->working_weight_g);
        $this->assertDatabaseMissing('bump_records', [
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $routineExercise->id,
        ]);
    }

    #[Test]
    public function confirmed_undos_skip_when_routine_weight_no_longer_matches_bump(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);
        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $routineExercise->update(['working_weight_g' => 90000]);
        $recordId = $workout->fresh()->bumpRecords->first()->id;

        $this->progressionService->applyConfirmedUndos($workout, [$recordId]);

        $this->assertSame(90000, $routineExercise->fresh()->working_weight_g);
        $this->assertNull($workout->fresh()->bumpRecords->first()->undone_at);
    }

    #[Test]
    public function re_eval_omits_undo_when_routine_weight_moved_past_bump(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);
        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $routineExercise->update(['working_weight_g' => 90000]);
        $set->update(['reps' => 4]);

        $session = $this->progressionService->reEvaluateProgression($workout->fresh());

        $this->assertCount(0, $session->undos);
    }

    #[Test]
    public function finish_ignores_dropsets_for_carry_forward_and_bumps(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $working = $routine->blocks->first()->setGroups->firstWhere('type', SetGroupType::Working);
        RoutineDropsetSegment::create([
            'routine_set_group_id' => $working->id,
            'set_index' => 0,
            'position' => 1,
            'weight_g' => 90000,
        ]);
        RoutineDropsetSegment::create([
            'routine_set_group_id' => $working->id,
            'set_index' => 0,
            'position' => 2,
            'weight_g' => 85000,
        ]);

        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 10, weightGrams: null, segmentWeightGrams: [90000, 85000]);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
        $this->assertSame(80000, $routineExercise->fresh()->working_weight_g);
    }

    /**
     * @return array{0: Routine, 1: RoutineBlockExercise}
     */
    private function seedRoutine(
        int $workingWeightG,
        int $prescribedReps,
        int $achievementFloor,
        BumpWhen $bumpWhen = BumpWhen::AnySet,
        int $setCount = 1,
    ): array {
        $user = User::factory()->create([
            'achievement_floor_default' => $achievementFloor,
            'bump_when_default' => $bumpWhen,
        ]);
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
        ]);
        $routineExercise = RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => $workingWeightG,
            'prescribed_reps' => $prescribedReps,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => $setCount,
        ]);

        return [$routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']), $routineExercise];
    }

    private function firstSet(int $workoutId): WorkoutSet
    {
        return $this->workingSets($workoutId)->first();
    }

    /**
     * @return Collection<int, WorkoutSet>
     */
    private function workingSets(int $workoutId): Collection
    {
        return WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workoutId))
            ->orderBy('set_index')
            ->get();
    }
}
