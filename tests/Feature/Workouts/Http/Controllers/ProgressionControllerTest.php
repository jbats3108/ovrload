<?php

namespace Tests\Feature\Workouts\Http\Controllers;

use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutProgressionService;
use App\Workouts\Services\WorkoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class ProgressionControllerTest extends TestCase
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
    public function finish_with_bumps_redirects_to_progression_and_stores_session(): void
    {
        [$workout, $routineExercise] = $this->createFinishedEligibleWorkout();

        $this->actingAs($this->user)
            ->post(route('workouts.finish', $workout))
            ->assertRedirect(route('workouts.progression', $workout));

        $this->assertSame(WorkoutStatus::Finished, $workout->fresh()->status);
        $this->assertNotEmpty(session("workout_progression.{$workout->id}"));
        $this->assertSame(
            $routineExercise->id,
            session("workout_progression.{$workout->id}")[0]['routine_block_exercise_id']
                ?? session("workout_progression.{$workout->id}")[0]['routineBlockExerciseId']
                ?? null
        );
    }

    #[Test]
    public function show_renders_progression_when_session_present(): void
    {
        [$workout] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workout));

        $this->actingAs($this->user)
            ->get(route('workouts.progression', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('workouts/Progression')
                ->where('progression.workout_id', $workout->ulid)
                ->has('progression.bumps', 1));
    }

    #[Test]
    public function show_renders_undo_proposals_when_present(): void
    {
        [$workout, $routineExercise] = $this->createFinishedEligibleWorkout();
        $workoutService = app(WorkoutService::class);
        $progressionService = app(WorkoutProgressionService::class);
        $bumps = $workoutService->finishWorkout($workout);
        $progressionService->applyConfirmedBumps($workout, $bumps, [$routineExercise->id]);

        $set = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();
        $set->update(['reps' => 4]);

        $reEval = $progressionService->reEvaluateProgression($workout->fresh());
        $progressionService->storeProgressionSession($workout, $reEval);

        $this->actingAs($this->user)
            ->get(route('workouts.progression', $workout))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('progression.undos', 1));
    }

    #[Test]
    public function show_redirects_to_dashboard_without_session(): void
    {
        [$workout] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workout));
        session()->forget("workout_progression.{$workout->id}");

        $this->actingAs($this->user)
            ->get(route('workouts.progression', $workout))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'No progression to review for that workout.');
    }

    #[Test]
    public function show_redirects_to_dashboard_when_not_finished(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->withSession(["workout_progression.{$workout->id}" => [
                [
                    'routine_block_exercise_id' => 1,
                    'from_weight_g' => 80000,
                    'to_weight_g' => 82500,
                    'exercise_name' => 'Squat',
                ],
            ]])
            ->get(route('workouts.progression', $workout))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Progression is only available for finished workouts.');
    }

    #[Test]
    public function apply_rejects_stale_session_after_newer_finish(): void
    {
        [$workoutA, $routineExercise] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workoutA));
        $this->assertNotEmpty(session("workout_progression.{$workoutA->id}"));

        $workoutB = app(WorkoutService::class)->createWorkout($workoutA->routine->fresh());
        $setB = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workoutB->id))
            ->firstOrFail();
        app(WorkoutService::class)->completeSet($setB, reps: 6, weightGrams: 80000);
        $this->actingAs($this->user)->post(route('workouts.finish', $workoutB));

        $this->assertNull(session("workout_progression.{$workoutA->id}"));

        $this->withSession([
            "workout_progression.{$workoutA->id}" => [[
                'routine_block_exercise_id' => $routineExercise->id,
                'from_weight_g' => 80000,
                'to_weight_g' => 82500,
                'exercise_name' => 'Squat',
            ]],
        ])
            ->post(route('workouts.progression.apply', $workoutA), [
                'routine_block_exercise_ids' => [$routineExercise->id],
            ])
            ->assertForbidden();

        $this->assertSame(80000, $routineExercise->fresh()->working_weight_g);
    }

    #[Test]
    public function show_redirects_when_workout_is_no_longer_latest(): void
    {
        [$workoutA] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workoutA));

        $workoutB = app(WorkoutService::class)->createWorkout($workoutA->routine->fresh());
        $setB = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workoutB->id))
            ->firstOrFail();
        app(WorkoutService::class)->completeSet($setB, reps: 5, weightGrams: 80000);
        app(WorkoutService::class)->finishWorkout($workoutB);

        $this->withSession([
            "workout_progression.{$workoutA->id}" => [[
                'routine_block_exercise_id' => 1,
                'from_weight_g' => 80000,
                'to_weight_g' => 82500,
                'exercise_name' => 'Squat',
            ]],
        ])
            ->get(route('workouts.progression', $workoutA))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Progression is only available for the latest finished workout.');

        $this->assertNull(session("workout_progression.{$workoutA->id}"));
    }

    #[Test]
    public function skip_clears_stale_session_even_when_not_latest(): void
    {
        [$workoutA] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workoutA));

        $workoutB = app(WorkoutService::class)->createWorkout($workoutA->routine->fresh());
        $setB = WorkoutSet::query()
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workoutB->id))
            ->firstOrFail();
        app(WorkoutService::class)->completeSet($setB, reps: 5, weightGrams: 80000);
        app(WorkoutService::class)->finishWorkout($workoutB);

        $this->withSession([
            "workout_progression.{$workoutA->id}" => [[
                'routine_block_exercise_id' => 1,
                'from_weight_g' => 80000,
                'to_weight_g' => 82500,
                'exercise_name' => 'Squat',
            ]],
        ])
            ->post(route('workouts.progression.skip', $workoutA))
            ->assertRedirect(route('dashboard'));

        $this->assertNull(session("workout_progression.{$workoutA->id}"));
    }

    #[Test]
    public function apply_clears_session_and_redirects_to_dashboard(): void
    {
        [$workout, $routineExercise] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workout));

        $this->actingAs($this->user)
            ->post(route('workouts.progression.apply', $workout), [
                'routine_block_exercise_ids' => [$routineExercise->id],
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertNull(session("workout_progression.{$workout->id}"));
    }

    #[Test]
    public function skip_clears_session_and_redirects_to_dashboard(): void
    {
        [$workout] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workout));

        $this->actingAs($this->user)
            ->post(route('workouts.progression.skip', $workout))
            ->assertRedirect(route('dashboard'));

        $this->assertNull(session("workout_progression.{$workout->id}"));
    }

    #[Test]
    public function non_owner_cannot_apply_progression(): void
    {
        [$workout, $routineExercise] = $this->createFinishedEligibleWorkout();
        $this->actingAs($this->user)->post(route('workouts.finish', $workout));

        $this->actingAs($this->secondUser)
            ->post(route('workouts.progression.apply', $workout), [
                'routine_block_exercise_ids' => [$routineExercise->id],
            ])
            ->assertNotFound();
    }

    #[Test]
    public function in_progress_workout_cannot_apply_progression(): void
    {
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->post(route('workouts.progression.apply', $workout), [
                'routine_block_exercise_ids' => [1],
            ])
            ->assertForbidden();
    }
}
