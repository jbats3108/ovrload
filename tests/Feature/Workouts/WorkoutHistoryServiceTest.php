<?php

namespace Tests\Feature\Workouts;

use App\Routines\Models\Routine;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Enums\SetGroupType;
use App\Workouts\Data\History\UpdateWorkoutHistoryData;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutSet;
use App\Workouts\Services\WorkoutHistoryService;
use App\Workouts\Services\WorkoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\SeedsPlayableRoutineBlock;
use Tests\TestCase;

class WorkoutHistoryServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPlayableRoutineBlock;

    private WorkoutHistoryService $history;

    private WorkoutService $workouts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->history = app(WorkoutHistoryService::class);
        $this->workouts = app(WorkoutService::class);
    }

    #[Test]
    public function update_rejects_warm_up_sets(): void
    {
        $routine = Routine::factory()->create();
        [, $routineExercise] = $this->seedPlayableRoutineBlock($routine, setCount: 1, restSeconds: null);
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $routineExercise->routine_block_id,
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

        $workout = $this->workouts->createWorkout($routine);
        $warmUpSet = WorkoutSet::query()
            ->whereHas('setGroup', fn ($q) => $q->where('type', SetGroupType::WarmUp))
            ->whereHas('setGroup.block', fn ($q) => $q->where('workout_id', $workout->id))
            ->firstOrFail();
        $workingSet = $this->firstWorkingSet($workout->id);

        $this->workouts->completeSet($warmUpSet, reps: 5, weightGrams: 32000);
        $this->workouts->completeSet($workingSet, reps: 5, weightGrams: 80000);
        $this->workouts->finishWorkout($workout);

        $this->expectException(WorkoutServiceException::class);
        $this->expectExceptionMessage(WorkoutHistoryService::WARM_UP_SETS_READ_ONLY_ERROR);

        $this->history->updateWorkingSets(
            $workout->fresh(),
            UpdateWorkoutHistoryData::from([
                'sets' => [
                    [
                        'id' => $warmUpSet->id,
                        'reps' => 6,
                        'weight_kg' => 40,
                    ],
                ],
            ]),
        );
    }

    #[Test]
    public function update_replaces_dropset_segments(): void
    {
        $workout = $this->createFinishedDropsetWorkout();
        $set = $this->firstWorkingSet($workout->id);

        $this->history->updateWorkingSets(
            $workout,
            UpdateWorkoutHistoryData::from([
                'sets' => [
                    [
                        'id' => $set->id,
                        'reps' => 10,
                        'segments' => [
                            ['weight_kg' => 16],
                            ['weight_kg' => 12],
                            ['weight_kg' => 8],
                        ],
                    ],
                ],
            ]),
        );

        $set->refresh()->load('segments');

        $this->assertSame(10, $set->reps);
        $this->assertNull($set->weight_g);
        $this->assertFalse($set->is_skipped);
        $this->assertSame([16000, 12000, 8000], $set->segments->pluck('weight_g')->all());
    }

    #[Test]
    public function update_can_change_dropset_reps_without_replacing_segments(): void
    {
        $workout = $this->createFinishedDropsetWorkout([18000, 14000]);
        $set = $this->firstWorkingSet($workout->id)->load('segments');
        $originalSegments = $set->segments->pluck('weight_g')->all();

        $this->history->updateWorkingSets(
            $workout,
            UpdateWorkoutHistoryData::from([
                'sets' => [
                    [
                        'id' => $set->id,
                        'reps' => 9,
                    ],
                ],
            ]),
        );

        $set->refresh()->load('segments');

        $this->assertSame(9, $set->reps);
        $this->assertFalse($set->is_skipped);
        $this->assertSame($originalSegments, $set->segments->pluck('weight_g')->all());
    }

    #[Test]
    public function update_fills_completed_at_when_missing(): void
    {
        $routine = Routine::factory()->create();
        $this->seedPlayableRoutineBlock($routine, setCount: 1, restSeconds: null);
        $workout = $this->workouts->createWorkout($routine);
        $set = $this->firstWorkingSet($workout->id);
        $this->workouts->completeSet($set, reps: 5, weightGrams: 80000);
        $this->workouts->finishWorkout($workout);

        $set->forceFill(['completed_at' => null])->save();
        $this->assertNull($set->fresh()->completed_at);

        $this->history->updateWorkingSets(
            $workout->fresh(),
            UpdateWorkoutHistoryData::from([
                'sets' => [
                    [
                        'id' => $set->id,
                        'reps' => 4,
                        'weight_kg' => 80,
                    ],
                ],
            ]),
        );

        $this->assertNotNull($set->fresh()->completed_at);
        $this->assertSame(4, $set->fresh()->reps);
    }

    /**
     * @param  list<int>  $loggedSegmentGrams
     */
    private function createFinishedDropsetWorkout(array $loggedSegmentGrams = [18000, 14000, 10000]): Workout
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

        $workout = $this->workouts->createWorkout($routine);
        $set = $this->firstWorkingSet($workout->id);
        $this->workouts->completeSet(
            $set,
            reps: 12,
            weightGrams: null,
            segmentWeightGrams: $loggedSegmentGrams,
        );
        $this->workouts->finishWorkout($workout);

        return $workout->fresh();
    }
}
