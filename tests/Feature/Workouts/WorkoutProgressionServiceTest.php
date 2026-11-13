<?php

namespace Tests\Feature\Workouts;

use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Shared\Enums\BlockType;
use App\Shared\Enums\PrescriptionMode;
use App\Shared\Enums\SetGroupType;
use App\Users\Enums\ProgressionStyle;
use App\Users\Models\User;
use App\Workouts\Data\Progression\BumpProposalData;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Models\BumpRecord;
use App\Workouts\Models\WorkoutBlock;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutProgressionService;
use App\Workouts\Services\WorkoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\DataCollection;
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
    public function finish_bump_from_weight_uses_carried_forward_routine_weight(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);

        $this->workoutService->completeSet($set, reps: 6, weightGrams: 85000);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(1, $bumps);
        $this->assertSame(85000, $routineExercise->fresh()->working_weight_g);
        $this->assertSame(85000, $bumps->first()->fromWeightG);
        $this->assertSame(87500, $bumps->first()->toWeightG);
    }

    #[Test]
    public function finish_ignores_timed_single_sets_for_progression(): void
    {
        $user = User::factory()->create(['achievement_floor_default' => 4]);
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'type' => BlockType::Single,
            'is_superset' => false,
        ]);
        $routineExercise = RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => 0,
            'prescription_mode' => PrescriptionMode::Duration,
            'prescribed_duration_seconds' => 45,
            'prescribed_reps' => null,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
            'rest_seconds' => 60,
        ]);

        $workout = $this->workoutService->createWorkout($routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']));
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, durationSeconds: 45, weightGrams: 0);
        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
        $this->assertSame(0, $routineExercise->fresh()->working_weight_g);
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
    public function progressive_overload_skips_finish_bump_when_top_set_misses_target(): void
    {
        [$routine] = $this->seedRoutine(
            workingWeightG: 20000,
            prescribedReps: 6,
            achievementFloor: 4,
            progressionStyle: ProgressionStyle::ProgressiveOverload,
            setCount: 3,
        );
        $workout = $this->workoutService->createWorkout($routine);
        $this->assertSame(ProgressionStyle::ProgressiveOverload, $workout->progression_style);

        $sets = $this->workingSets($workout->id);
        $this->workoutService->completeSet($sets[0], reps: 6, weightGrams: 20000);
        $this->workoutService->completeSet($sets[1], reps: 6, weightGrams: 22500);
        $this->workoutService->completeSet($sets[2], reps: 4, weightGrams: 25000);

        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
    }

    #[Test]
    public function progressive_overload_skips_finish_bump_when_final_set_is_below_session_top(): void
    {
        [$routine] = $this->seedRoutine(
            workingWeightG: 80000,
            prescribedReps: 6,
            achievementFloor: 4,
            progressionStyle: ProgressionStyle::ProgressiveOverload,
            setCount: 3,
        );
        $workout = $this->workoutService->createWorkout($routine);

        $sets = $this->workingSets($workout->id);
        $this->workoutService->completeSet($sets[0], reps: 6, weightGrams: 85000);
        $this->workoutService->completeSet($sets[1], reps: 6, weightGrams: 82500);
        $this->workoutService->completeSet($sets[2], reps: 6, weightGrams: 82500);

        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(0, $bumps);
    }

    #[Test]
    public function progressive_overload_offers_finish_bump_when_final_set_is_at_session_top(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(
            workingWeightG: 80000,
            prescribedReps: 6,
            achievementFloor: 4,
            progressionStyle: ProgressionStyle::ProgressiveOverload,
            setCount: 3,
        );
        $workout = $this->workoutService->createWorkout($routine);

        $sets = $this->workingSets($workout->id);
        $this->workoutService->completeSet($sets[0], reps: 6, weightGrams: 80000);
        $this->workoutService->completeSet($sets[1], reps: 6, weightGrams: 82500);
        $this->workoutService->completeSet($sets[2], reps: 6, weightGrams: 85000);

        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(1, $bumps);
        $this->assertSame($routineExercise->id, $bumps->first()->routineBlockExerciseId);
    }

    #[Test]
    public function progressive_overload_offers_finish_bump_when_last_top_set_hits_target(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(
            workingWeightG: 20000,
            prescribedReps: 6,
            achievementFloor: 4,
            progressionStyle: ProgressionStyle::ProgressiveOverload,
            setCount: 3,
        );
        $workout = $this->workoutService->createWorkout($routine);

        $sets = $this->workingSets($workout->id);
        $this->workoutService->completeSet($sets[0], reps: 6, weightGrams: 20000);
        $this->workoutService->completeSet($sets[1], reps: 6, weightGrams: 20000);
        $this->workoutService->completeSet($sets[2], reps: 6, weightGrams: 20000);

        $bumps = $this->workoutService->finishWorkout($workout);

        $this->assertCount(1, $bumps);
        $this->assertSame($routineExercise->id, $bumps->first()->routineBlockExerciseId);
    }

    #[Test]
    public function straight_sets_offers_finish_bump_when_any_set_hit_target(): void
    {
        [$routine] = $this->seedRoutine(
            workingWeightG: 20000,
            prescribedReps: 6,
            achievementFloor: 4,
            progressionStyle: ProgressionStyle::StraightSets,
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
        ProgressionStyle $progressionStyle = ProgressionStyle::StraightSets,
        int $setCount = 1,
    ): array {
        $user = User::factory()->create([
            'achievement_floor_default' => $achievementFloor,
        ]);
        $routine = Routine::factory()->create([
            'user_id' => $user->id,
            'progression_style' => $progressionStyle,
        ]);
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

    #[Test]
    public function pull_progression_session_returns_null_when_session_is_empty(): void
    {
        [$routine] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);

        $this->assertNull($this->progressionService->pullProgressionSession($workout));
        $this->assertFalse($this->progressionService->hasProgressionSession($workout));
    }

    #[Test]
    public function pull_progression_session_restores_bumps_and_undos(): void
    {
        [$routine] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);

        session([
            "workout_progression.{$workout->id}" => [[
                'routine_block_exercise_id' => 11,
                'exercise_name' => 'Squat',
                'from_weight_g' => 80000,
                'to_weight_g' => 82500,
            ]],
            "workout_progression_undos.{$workout->id}" => [[
                'bump_record_id' => 22,
                'routine_block_exercise_id' => 11,
                'exercise_name' => 'Squat',
                'from_weight_g' => 82500,
                'to_weight_g' => 80000,
            ]],
        ]);

        $this->assertTrue($this->progressionService->hasProgressionSession($workout));

        $session = $this->progressionService->pullProgressionSession($workout);

        $this->assertNotNull($session);
        $this->assertCount(1, $session->bumps);
        $this->assertCount(1, $session->undos);
        $this->assertSame(11, $session->bumps->first()->routineBlockExerciseId);
        $this->assertSame(22, $session->undos->first()->bumpRecordId);
        $this->assertFalse($this->progressionService->hasProgressionSession($workout));
    }

    #[Test]
    public function pull_progression_session_supports_bumps_only_or_undos_only(): void
    {
        [$routine] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);

        session([
            "workout_progression.{$workout->id}" => [[
                'routine_block_exercise_id' => 11,
                'exercise_name' => 'Squat',
                'from_weight_g' => 80000,
                'to_weight_g' => 82500,
            ]],
        ]);

        $bumpsOnly = $this->progressionService->pullProgressionSession($workout);
        $this->assertNotNull($bumpsOnly);
        $this->assertCount(1, $bumpsOnly->bumps);
        $this->assertCount(0, $bumpsOnly->undos);

        session([
            "workout_progression_undos.{$workout->id}" => [[
                'bump_record_id' => 22,
                'routine_block_exercise_id' => 11,
                'exercise_name' => 'Squat',
                'from_weight_g' => 82500,
                'to_weight_g' => 80000,
            ]],
        ]);

        $undosOnly = $this->progressionService->pullProgressionSession($workout);
        $this->assertNotNull($undosOnly);
        $this->assertCount(0, $undosOnly->bumps);
        $this->assertCount(1, $undosOnly->undos);
    }

    #[Test]
    public function pull_progression_session_returns_null_for_empty_arrays_or_non_arrays(): void
    {
        [$routine] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);

        session([
            "workout_progression.{$workout->id}" => [],
            "workout_progression_undos.{$workout->id}" => [],
        ]);
        $this->assertFalse($this->progressionService->hasProgressionSession($workout));
        $this->assertNull($this->progressionService->pullProgressionSession($workout));

        session([
            "workout_progression.{$workout->id}" => 'not-an-array',
            "workout_progression_undos.{$workout->id}" => 'not-an-array',
        ]);
        $this->assertFalse($this->progressionService->hasProgressionSession($workout));
        $this->assertNull($this->progressionService->pullProgressionSession($workout));
    }

    #[Test]
    public function has_progression_session_is_true_for_bumps_only_or_undos_only(): void
    {
        [$routine] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);

        session([
            "workout_progression.{$workout->id}" => [[
                'routine_block_exercise_id' => 11,
                'exercise_name' => 'Squat',
                'from_weight_g' => 80000,
                'to_weight_g' => 82500,
            ]],
        ]);
        $this->assertTrue($this->progressionService->hasProgressionSession($workout));

        session()->forget("workout_progression.{$workout->id}");
        session([
            "workout_progression_undos.{$workout->id}" => [[
                'bump_record_id' => 22,
                'routine_block_exercise_id' => 11,
                'exercise_name' => 'Squat',
                'from_weight_g' => 82500,
                'to_weight_g' => 80000,
            ]],
        ]);
        $this->assertTrue($this->progressionService->hasProgressionSession($workout));
    }

    #[Test]
    public function re_evaluate_defaults_to_collecting_new_bumps(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);

        $session = $this->progressionService->reEvaluateProgression($workout->fresh());

        $this->assertCount(1, $session->bumps);
        $this->assertSame($routineExercise->id, $session->bumps->first()->routineBlockExerciseId);
    }

    #[Test]
    public function finish_still_processes_routine_blocks_after_an_earlier_ad_hoc_block(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $routineBlock = $workout->blocks->firstWhere('is_ad_hoc', false);
        $this->assertNotNull($routineBlock);

        $adHocExercise = Exercise::factory()->shared()->create();
        $adHocBlock = $this->workoutService->addAdHocExercise($workout, $adHocExercise->id);

        // Force ad-hoc first in position order so continue (not break) is required.
        // Keep routine block position aligned with the workout block it maps to.
        $routineBlock->update(['position' => 99]);
        $adHocBlock->update(['position' => 1]);
        $routineBlock->update(['position' => 2]);
        $routine->blocks->first()->update(['position' => 2]);

        $set = WorkoutSet::query()
            ->whereHas(
                'setGroup.block',
                fn ($query) => $query->where('workout_id', $workout->id)->where('is_ad_hoc', false),
            )
            ->orderBy('set_index')
            ->first();
        $this->assertNotNull($set);

        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout->fresh());

        $this->assertCount(1, $bumps);
        $this->assertSame($routineExercise->id, $bumps->first()->routineBlockExerciseId);
    }

    #[Test]
    public function confirmed_bumps_continue_after_a_skipped_mismatch(): void
    {
        [$routine, $firstExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $secondExercise = $this->addRoutineExercise($routine, workingWeightG: 60000, prescribedReps: 6, position: 2);
        $workout = $this->workoutService->createWorkout($routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']));

        $firstExercise->update(['working_weight_g' => 100000]);

        $proposals = BumpProposalData::collect([
            new BumpProposalData(
                routineBlockExerciseId: $firstExercise->id,
                exerciseName: 'First',
                fromWeightG: 80000,
                toWeightG: 82500,
            ),
            new BumpProposalData(
                routineBlockExerciseId: $secondExercise->id,
                exerciseName: 'Second',
                fromWeightG: 60000,
                toWeightG: 62500,
            ),
        ], DataCollection::class);

        $this->progressionService->applyConfirmedBumps(
            $workout,
            $proposals,
            [$firstExercise->id, $secondExercise->id],
        );

        $this->assertSame(100000, $firstExercise->fresh()->working_weight_g);
        $this->assertSame(62500, $secondExercise->fresh()->working_weight_g);
        $this->assertDatabaseHas('bump_records', [
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $secondExercise->id,
            'from_weight_g' => 60000,
            'to_weight_g' => 62500,
        ]);
        $this->assertDatabaseMissing('bump_records', [
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $firstExercise->id,
        ]);
    }

    #[Test]
    public function confirmed_undos_continue_after_a_skipped_mismatch(): void
    {
        [$routine, $firstExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $secondExercise = $this->addRoutineExercise($routine, workingWeightG: 60000, prescribedReps: 6, position: 2);
        $workout = $this->workoutService->createWorkout($routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']));

        $firstRecord = BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $firstExercise->id,
            'from_weight_g' => 80000,
            'to_weight_g' => 82500,
        ]);
        $secondRecord = BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $secondExercise->id,
            'from_weight_g' => 60000,
            'to_weight_g' => 62500,
        ]);

        $firstExercise->update(['working_weight_g' => 82500]);
        $secondExercise->update(['working_weight_g' => 62500]);
        $firstExercise->update(['working_weight_g' => 90000]);

        $this->progressionService->applyConfirmedUndos($workout, [$firstRecord->id, $secondRecord->id]);

        $this->assertSame(90000, $firstExercise->fresh()->working_weight_g);
        $this->assertSame(60000, $secondExercise->fresh()->working_weight_g);
        $this->assertNull($firstRecord->fresh()->undone_at);
        $this->assertNotNull($secondRecord->fresh()->undone_at);
    }

    #[Test]
    public function re_eval_continues_collecting_undos_after_a_skipped_record(): void
    {
        [$routine, $firstExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $secondExercise = $this->addRoutineExercise($routine, workingWeightG: 60000, prescribedReps: 6, position: 2);
        $workout = $this->workoutService->createWorkout($routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']));

        BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $firstExercise->id,
            'from_weight_g' => 80000,
            'to_weight_g' => 82500,
        ]);
        $secondRecord = BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $secondExercise->id,
            'from_weight_g' => 60000,
            'to_weight_g' => 62500,
        ]);

        $firstExercise->update(['working_weight_g' => 90000]);
        $secondExercise->update(['working_weight_g' => 62500]);

        $workout = $workout->fresh(['blocks.blockExercises.sets.setGroup']);
        foreach ($workout->blocks as $block) {
            foreach ($block->blockExercises as $workoutExercise) {
                foreach ($workoutExercise->sets as $set) {
                    if ($set->setGroup?->type !== SetGroupType::Working) {
                        continue;
                    }

                    $this->workoutService->completeSet(
                        $set,
                        reps: 4,
                        weightGrams: $workoutExercise->working_weight_g,
                    );
                }
            }
        }

        $session = $this->progressionService->reEvaluateProgression($workout->fresh(), collectNewBumps: false);

        $this->assertCount(1, $session->undos);
        $this->assertSame($secondRecord->id, $session->undos->first()->bumpRecordId);
        $this->assertNotSame('Exercise', $session->undos->first()->exerciseName);
    }

    #[Test]
    public function re_eval_continues_collecting_undos_after_a_still_hit_target(): void
    {
        [$routine, $firstExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $secondExercise = $this->addRoutineExercise($routine, workingWeightG: 60000, prescribedReps: 6, position: 2);
        $workout = $this->workoutService->createWorkout($routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']));

        BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $firstExercise->id,
            'from_weight_g' => 80000,
            'to_weight_g' => 82500,
        ]);
        $secondRecord = BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $secondExercise->id,
            'from_weight_g' => 60000,
            'to_weight_g' => 62500,
        ]);

        $firstExercise->update(['working_weight_g' => 82500]);
        $secondExercise->update(['working_weight_g' => 62500]);

        $workout = $workout->fresh(['blocks.blockExercises.sets.setGroup']);
        foreach ($workout->blocks as $block) {
            foreach ($block->blockExercises as $workoutExercise) {
                foreach ($workoutExercise->sets as $set) {
                    if ($set->setGroup?->type !== SetGroupType::Working) {
                        continue;
                    }

                    $hitsTarget = $workoutExercise->exercise_id === $firstExercise->exercise_id;
                    $this->workoutService->completeSet(
                        $set,
                        reps: $hitsTarget ? 6 : 4,
                        weightGrams: $workoutExercise->working_weight_g,
                    );
                }
            }
        }

        $session = $this->progressionService->reEvaluateProgression($workout->fresh(), collectNewBumps: false);

        $this->assertCount(1, $session->undos);
        $this->assertSame($secondRecord->id, $session->undos->first()->bumpRecordId);
    }

    #[Test]
    public function re_eval_finds_workout_exercise_by_position_when_exercise_id_diverged(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);
        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $replacement = Exercise::factory()->create();
        $workoutExercise = $workout->fresh(['blocks.blockExercises'])->blocks->first()->blockExercises->first();
        $workoutExercise->update([
            'exercise_id' => $replacement->id,
            'exercise_name' => 'Replacement Lift',
        ]);
        $set->update(['reps' => 4]);

        $session = $this->progressionService->reEvaluateProgression($workout->fresh(), collectNewBumps: false);

        $this->assertCount(1, $session->undos);
        $this->assertSame($routineExercise->id, $session->undos->first()->routineBlockExerciseId);
    }

    #[Test]
    public function re_eval_finds_workout_exercise_by_exercise_id_when_position_diverged(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);
        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $workoutExercise = $workout->fresh(['blocks.blockExercises'])->blocks->first()->blockExercises->first();
        $workoutExercise->update(['position' => 9]);
        $set->update(['reps' => 4]);

        $session = $this->progressionService->reEvaluateProgression($workout->fresh(), collectNewBumps: false);

        $this->assertCount(1, $session->undos);
        $this->assertSame($routineExercise->id, $session->undos->first()->routineBlockExerciseId);
    }

    #[Test]
    public function re_eval_skips_orphan_workout_blocks_without_matching_routine_block(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout);
        $this->progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $matchedBlock = $workout->fresh()->blocks->first();
        $matchedBlock->update(['position' => 2]);
        $routine->blocks->first()->update(['position' => 2]);
        WorkoutBlock::query()->create([
            'workout_id' => $workout->id,
            'position' => 1,
            'type' => BlockType::Single,
            'is_superset' => false,
            'is_ad_hoc' => false,
        ]);
        $set->update(['reps' => 4]);

        $session = $this->progressionService->reEvaluateProgression($workout->fresh(), collectNewBumps: false);

        $this->assertCount(1, $session->undos);
        $this->assertSame($routineExercise->id, $session->undos->first()->routineBlockExerciseId);
    }

    #[Test]
    public function carry_forward_does_not_rewrite_when_highest_achieved_equals_routine_weight(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 4, weightGrams: 80000);

        $routineExercise->refresh();
        $updatedAt = $routineExercise->updated_at;

        Carbon::setTestNow('2026-01-01 12:05:00');
        $this->workoutService->finishWorkout($workout);

        $fresh = $routineExercise->fresh();
        $this->assertSame(80000, $fresh->working_weight_g);
        $this->assertTrue($updatedAt->equalTo($fresh->updated_at));

        Carbon::setTestNow();
    }

    #[Test]
    public function finish_still_processes_later_blocks_after_a_null_routine_match(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $matchedBlock = $workout->blocks->first();
        $matchedBlock->update(['position' => 2]);
        $routine->blocks->first()->update(['position' => 2]);
        WorkoutBlock::query()->create([
            'workout_id' => $workout->id,
            'position' => 1,
            'type' => BlockType::Single,
            'is_superset' => false,
            'is_ad_hoc' => false,
        ]);

        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 80000);
        $bumps = $this->workoutService->finishWorkout($workout->fresh());

        $this->assertCount(1, $bumps);
        $this->assertSame($routineExercise->id, $bumps->first()->routineBlockExerciseId);
    }

    #[Test]
    public function re_eval_can_return_multiple_undo_proposals(): void
    {
        [$routine, $firstExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $secondExercise = $this->addRoutineExercise($routine, workingWeightG: 60000, prescribedReps: 6, position: 2);
        $workout = $this->workoutService->createWorkout($routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']));

        BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $firstExercise->id,
            'from_weight_g' => 80000,
            'to_weight_g' => 82500,
        ]);
        BumpRecord::query()->create([
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $secondExercise->id,
            'from_weight_g' => 60000,
            'to_weight_g' => 62500,
        ]);
        $firstExercise->update(['working_weight_g' => 82500]);
        $secondExercise->update(['working_weight_g' => 62500]);

        $workout = $workout->fresh(['blocks.blockExercises.sets.setGroup']);
        foreach ($workout->blocks as $block) {
            foreach ($block->blockExercises as $workoutExercise) {
                foreach ($workoutExercise->sets as $set) {
                    if ($set->setGroup?->type !== SetGroupType::Working) {
                        continue;
                    }

                    $this->workoutService->completeSet(
                        $set,
                        reps: 4,
                        weightGrams: $workoutExercise->working_weight_g,
                    );
                }
            }
        }

        $session = $this->progressionService->reEvaluateProgression($workout->fresh(), collectNewBumps: false);

        $this->assertCount(2, $session->undos);
    }

    #[Test]
    public function match_prefers_exercise_id_over_position_when_both_could_match(): void
    {
        $user = User::factory()->create(['achievement_floor_default' => 4]);
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'type' => BlockType::Superset,
            'is_superset' => true,
        ]);
        $firstExercise = RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 1,
            'working_weight_g' => 80000,
            'prescribed_reps' => 6,
        ]);
        $secondExercise = RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'position' => 2,
            'working_weight_g' => 60000,
            'prescribed_reps' => 6,
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 1,
        ]);

        $routine = $routine->fresh(['user', 'blocks.blockExercises', 'blocks.setGroups']);
        $workout = $this->workoutService->createWorkout($routine);
        $workoutExercises = $workout->blocks->first()->blockExercises;
        $firstWorkoutExercise = $workoutExercises->firstWhere('exercise_id', $firstExercise->exercise_id);
        $secondWorkoutExercise = $workoutExercises->firstWhere('exercise_id', $secondExercise->exercise_id);
        $this->assertNotNull($firstWorkoutExercise);
        $this->assertNotNull($secondWorkoutExercise);
        $firstWorkoutExercise->update(['position' => 99]);
        $secondWorkoutExercise->update(['position' => 1]);
        $firstWorkoutExercise->update(['position' => 2]);

        $set = WorkoutSet::query()
            ->where('workout_block_exercise_id', $secondWorkoutExercise->id)
            ->orderBy('set_index')
            ->first();
        $this->assertNotNull($set);
        $this->workoutService->completeSet($set, reps: 6, weightGrams: 60000);

        $session = $this->progressionService->reEvaluateProgression($workout->fresh());

        $this->assertCount(1, $session->bumps);
        $this->assertSame($secondExercise->id, $session->bumps->first()->routineBlockExerciseId);
        $this->assertNotSame($firstExercise->id, $session->bumps->first()->routineBlockExerciseId);
    }

    #[Test]
    public function carry_forward_ignores_incomplete_working_sets(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4, setCount: 2);
        $workout = $this->workoutService->createWorkout($routine);
        $sets = $this->workingSets($workout->id);
        $this->assertCount(2, $sets);

        $sets[0]->update([
            'reps' => 6,
            'weight_g' => 100000,
            'completed_at' => null,
        ]);
        $this->workoutService->completeSet($sets[1], reps: 4, weightGrams: 85000);
        $this->workoutService->finishWorkout($workout);

        $this->assertSame(85000, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function carry_forward_uses_snapshot_floor_and_includes_exact_floor_reps(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $workoutExercise = $workout->blocks->first()->blockExercises->first();
        $workoutExercise->update(['achievement_floor' => null]);

        $set = $this->firstSet($workout->id);
        $this->workoutService->completeSet($set, reps: 1, weightGrams: 90000);
        $this->workoutService->finishWorkout($workout->fresh());

        $this->assertSame(90000, $routineExercise->fresh()->working_weight_g);

        [$routine2, $routineExercise2] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout2 = $this->workoutService->createWorkout($routine2);
        $workout2->blocks->first()->blockExercises->first()->update(['achievement_floor' => 5]);
        $set2 = $this->firstSet($workout2->id);
        $this->workoutService->completeSet($set2, reps: 3, weightGrams: 90000);
        $this->workoutService->finishWorkout($workout2);

        $this->assertSame(80000, $routineExercise2->fresh()->working_weight_g);

        [$routine3, $routineExercise3] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout3 = $this->workoutService->createWorkout($routine3);
        $set3 = $this->firstSet($workout3->id);
        $this->workoutService->completeSet($set3, reps: 4, weightGrams: 90000);
        $this->workoutService->finishWorkout($workout3);

        $this->assertSame(90000, $routineExercise3->fresh()->working_weight_g);
    }

    #[Test]
    public function carry_forward_default_floor_excludes_zero_rep_sets(): void
    {
        [$routine, $routineExercise] = $this->seedRoutine(workingWeightG: 80000, prescribedReps: 6, achievementFloor: 4);
        $workout = $this->workoutService->createWorkout($routine);
        $workout->blocks->first()->blockExercises->first()->update(['achievement_floor' => null]);

        $set = $this->firstSet($workout->id);
        $set->update([
            'reps' => 0,
            'weight_g' => 90000,
            'completed_at' => now(),
        ]);

        $this->progressionService->reEvaluateProgression($workout->fresh(), collectNewBumps: false);

        $this->assertSame(80000, $routineExercise->fresh()->working_weight_g);
    }

    private function addRoutineExercise(
        Routine $routine,
        int $workingWeightG,
        int $prescribedReps,
        int $position,
    ): RoutineBlockExercise {
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => $position,
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
            'set_count' => 1,
        ]);

        return $routineExercise;
    }
}
