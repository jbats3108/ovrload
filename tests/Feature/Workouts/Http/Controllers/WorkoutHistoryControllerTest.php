<?php

namespace Tests\Feature\Workouts\Http\Controllers;

use App\Routines\Models\Routine;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutProgressionService;
use App\Workouts\Services\WorkoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class WorkoutHistoryControllerTest extends TestCase
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
    public function index_lists_finished_workouts_only(): void
    {
        [$finished] = $this->createFinishedWorkout();
        $discarded = $this->createPlayableWorkout();
        app(WorkoutService::class)->discardWorkout($discarded);

        $this->actingAs($this->user)
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('history/Index')
                ->has('history.workouts', 1)
                ->where('history.workouts.0.id', $finished->ulid));
    }

    #[Test]
    public function index_filters_by_routine(): void
    {
        [$workoutA] = $this->createFinishedWorkout();
        [$workoutB, , $routineB] = $this->createFinishedWorkout();

        $this->actingAs($this->user)
            ->get(route('history.index', ['routine' => $routineB->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('history.workouts', 1)
                ->where('history.workouts.0.id', $workoutB->ulid)
                ->where('history.routine_slug', $routineB->slug));

        $this->assertNotSame($workoutA->routine_id, $routineB->id);
    }

    #[Test]
    public function show_renders_finished_workout_detail(): void
    {
        [$workout] = $this->createFinishedWorkout();

        $this->actingAs($this->user)
            ->get(route('history.show', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('history/Show')
                ->where('history.workout.id', $workout->ulid)
                ->where('history.can_re_evaluate', true));
    }

    #[Test]
    public function show_redirects_for_in_progress_workout(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->get(route('history.show', $workout))
            ->assertRedirect(route('history.index'))
            ->assertSessionHas('error', 'That workout is not in history yet.');
    }

    #[Test]
    public function editing_after_confirmed_bump_does_not_propose_second_bump(): void
    {
        [$workout, $routineExercise] = $this->createFinishedWorkout(reps: 6, weightGrams: 80000);
        $progressionService = app(WorkoutProgressionService::class);
        $session = $progressionService->reEvaluateProgression($workout);
        $progressionService->applyConfirmedBumps($workout, $session->bumps, [$routineExercise->id]);

        $set = $this->firstWorkingSet($workout->id);

        $this->actingAs($this->user)
            ->put(route('history.update', $workout), $this->historySetPayload($set, reps: 6, weightKg: 82.5))
            ->assertRedirect()
            ->assertSessionMissing("workout_progression.{$workout->id}");

        $this->assertSame(82500, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function editing_before_confirming_finish_bump_does_not_propose_bump_from_carry_forward_baseline(): void
    {
        [$workout, $routineExercise] = $this->createFinishedWorkout(reps: 6, weightGrams: 80000);
        $progressionService = app(WorkoutProgressionService::class);
        $progressionService->storeProgressionSession(
            $workout,
            $progressionService->reEvaluateProgression($workout),
        );

        $set = $this->firstWorkingSet($workout->id);

        $response = $this->actingAs($this->user)
            ->put(route('history.update', $workout), $this->historySetPayload($set, reps: 6, weightKg: 82.5));

        $response->assertRedirect();
        $this->assertStringNotContainsString(
            '/progression',
            (string) $response->headers->get('Location'),
            'History edit must not redirect to progression for a new bump',
        );

        $stored = session("workout_progression.{$workout->id}");
        $this->assertIsArray($stored);
        $this->assertSame(
            80000,
            $stored[0]['from_weight_g'] ?? $stored[0]['fromWeightG'] ?? null,
            'Finish bump session must be preserved, not replaced by a carry-forward baseline bump',
        );
        $this->assertSame(82500, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function eligible_set_edit_triggers_re_eval_and_progression_redirect(): void
    {
        [$workout, $routineExercise] = $this->createFinishedWorkout(reps: 6, weightGrams: 80000);
        $progressionService = app(WorkoutProgressionService::class);
        $session = $progressionService->reEvaluateProgression($workout);
        $progressionService->applyConfirmedBumps($workout, $session->bumps, [$routineExercise->id]);

        $set = $this->firstWorkingSet($workout->id);

        $this->actingAs($this->user)
            ->put(route('history.update', $workout), $this->historySetPayload($set, reps: 4, weightKg: 80))
            ->assertRedirect(route('workouts.progression', $workout));

        $this->assertNotEmpty(session("workout_progression_undos.{$workout->id}"));
    }

    #[Test]
    public function older_finished_workout_edit_does_not_re_evaluate(): void
    {
        [$older, $routineExercise, $routine] = $this->createFinishedWorkout(reps: 6, weightGrams: 80000);
        $older->update(['finished_at' => now()->subDay()]);

        $newer = app(WorkoutService::class)->createWorkout($routine);
        $newerSet = $this->firstWorkingSet($newer->id);
        app(WorkoutService::class)->completeSet($newerSet, reps: 5, weightGrams: 80000);
        app(WorkoutService::class)->finishWorkout($newer);

        $olderSet = $this->firstWorkingSet($older->id);

        $this->actingAs($this->user)
            ->put(route('history.update', $older), $this->historySetPayload($olderSet, reps: 3, weightKg: 80))
            ->assertRedirect();

        $this->assertNull(session("workout_progression.{$older->id}"));
        $this->assertSame(80000, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function non_owner_cannot_edit_history(): void
    {
        [$workout] = $this->createFinishedWorkout();
        $set = $this->firstWorkingSet($workout->id);

        $this->actingAs($this->secondUser)
            ->put(route('history.update', $workout), $this->historySetPayload($set, reps: 5, weightKg: 80))
            ->assertNotFound();
    }

    #[Test]
    public function history_set_edit_accepts_two_decimal_kg(): void
    {
        [$workout] = $this->createFinishedWorkout(reps: 5, weightGrams: 28000);
        $set = $this->firstWorkingSet($workout->id);

        $this->actingAs($this->user)
            ->put(route('history.update', $workout), $this->historySetPayload($set, reps: 5, weightKg: 28.75))
            ->assertRedirect();

        $this->assertSame(28750, $set->fresh()->weight_g);
    }

    #[Test]
    public function history_save_updates_multiple_working_sets_in_one_request(): void
    {
        $this->user->update([
            'progression_target_default' => 6,
            'achievement_floor_default' => 4,
        ]);

        $routine = Routine::factory()->withUser($this->user)->create();
        $this->seedPlayableRoutineBlock(
            $routine,
            setCount: 2,
            restSeconds: 90,
            workingWeightG: 60000,
            prescribedReps: 5,
            progressionTarget: 6,
            achievementFloor: 4,
        );

        $workoutService = app(WorkoutService::class);
        $workout = $workoutService->createWorkout($routine);
        $sets = WorkoutSet::query()
            ->whereHas('setGroup', fn ($q) => $q->where('type', SetGroupType::Working))
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $sets);

        foreach ($sets as $set) {
            $workoutService->completeSet($set, reps: 5, weightGrams: 60000);
        }
        $workoutService->finishWorkout($workout);

        $first = $sets[0];
        $second = $sets[1];

        $this->actingAs($this->user)
            ->put(route('history.update', $workout), [
                'sets' => [
                    ['id' => $first->id, 'reps' => 4, 'weight_kg' => 62.5],
                    ['id' => $second->id, 'reps' => 3, 'weight_kg' => 65],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(4, $first->fresh()->reps);
        $this->assertSame(62500, $first->fresh()->weight_g);
        $this->assertSame(3, $second->fresh()->reps);
        $this->assertSame(65000, $second->fresh()->weight_g);
    }

    #[Test]
    public function user_can_delete_finished_workout_from_history(): void
    {
        [$workout] = $this->createFinishedWorkout();

        $this->actingAs($this->user)
            ->delete(route('history.destroy', $workout))
            ->assertRedirect(route('history.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($workout);
    }

    #[Test]
    public function deleted_workout_no_longer_appears_in_history_index(): void
    {
        [$workout] = $this->createFinishedWorkout();

        $this->actingAs($this->user)
            ->delete(route('history.destroy', $workout))
            ->assertRedirect();

        $this->actingAs($this->user)
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->has('history.workouts', 0));
    }

    #[Test]
    public function non_owner_cannot_delete_history(): void
    {
        [$workout] = $this->createFinishedWorkout();

        $this->actingAs($this->secondUser)
            ->delete(route('history.destroy', $workout))
            ->assertNotFound();

        $this->assertNotSoftDeleted($workout);
    }

    #[Test]
    public function in_progress_workout_cannot_be_deleted_from_history(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->delete(route('history.destroy', $workout))
            ->assertForbidden();

        $this->assertNotSoftDeleted($workout);
    }

    #[Test]
    public function deleting_finished_workout_does_not_undo_progression_bumps(): void
    {
        [$workout, $routineExercise] = $this->createFinishedWorkout(reps: 6, weightGrams: 80000);
        $progressionService = app(WorkoutProgressionService::class);
        $session = $progressionService->reEvaluateProgression($workout);
        $progressionService->applyConfirmedBumps($workout, $session->bumps, [$routineExercise->id]);

        $this->assertSame(82500, $routineExercise->fresh()->working_weight_g);

        $this->actingAs($this->user)
            ->delete(route('history.destroy', $workout))
            ->assertRedirect(route('history.index'));

        $this->assertSoftDeleted($workout);
        $this->assertSame(82500, $routineExercise->fresh()->working_weight_g);
        $this->assertDatabaseHas('bump_records', [
            'workout_id' => $workout->id,
            'routine_block_exercise_id' => $routineExercise->id,
            'from_weight_g' => 80000,
            'to_weight_g' => 82500,
            'undone_at' => null,
        ]);
    }

    #[Test]
    public function deleting_latest_then_editing_prior_does_not_undo_past_later_carry_forward(): void
    {
        [$older, $routineExercise, $routine] = $this->createFinishedWorkout(reps: 6, weightGrams: 80000);
        $older->update(['finished_at' => now()->subDay()]);
        $progressionService = app(WorkoutProgressionService::class);
        $session = $progressionService->reEvaluateProgression($older);
        $progressionService->applyConfirmedBumps($older, $session->bumps, [$routineExercise->id]);
        $this->assertSame(82500, $routineExercise->fresh()->working_weight_g);

        $newer = app(WorkoutService::class)->createWorkout($routine);
        $newerSet = $this->firstWorkingSet($newer->id);
        app(WorkoutService::class)->completeSet($newerSet, reps: 5, weightGrams: 90000);
        app(WorkoutService::class)->finishWorkout($newer);
        $this->assertSame(90000, $routineExercise->fresh()->working_weight_g);

        $this->actingAs($this->user)
            ->delete(route('history.destroy', $newer))
            ->assertRedirect(route('history.index'));

        $this->assertTrue($older->fresh()->isEligibleForProgressionReEval());

        $olderSet = $this->firstWorkingSet($older->id);
        $this->actingAs($this->user)
            ->put(route('history.update', $older), $this->historySetPayload($olderSet, reps: 3, weightKg: 80))
            ->assertRedirect();

        $this->assertNull(session("workout_progression_undos.{$older->id}"));
        $this->assertSame(90000, $routineExercise->fresh()->working_weight_g);
        $this->assertNull($older->fresh()->bumpRecords->first()->undone_at);
    }

    /**
     * @return array{sets: list<array{id: int, reps: int, weight_kg: float}>}
     */
    private function historySetPayload(WorkoutSet $set, int $reps, float $weightKg): array
    {
        return [
            'sets' => [
                [
                    'id' => $set->id,
                    'reps' => $reps,
                    'weight_kg' => $weightKg,
                ],
            ],
        ];
    }
}
