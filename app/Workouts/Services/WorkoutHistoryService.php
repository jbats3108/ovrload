<?php

namespace App\Workouts\Services;

use App\Shared\Enums\SetGroupType;
use App\Workouts\Data\CompleteWorkoutSetData;
use App\Workouts\Data\Progression\ProgressionSessionData;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutSet;
use Illuminate\Support\Facades\DB;

class WorkoutHistoryService
{
    public const WORKOUT_NOT_FINISHED_ERROR = 'Only finished workouts can be edited in history';

    public const WARM_UP_SETS_READ_ONLY_ERROR = 'Warm-up sets cannot be edited in history';

    public function __construct(
        private readonly WorkoutProgressionService $progressionService,
    ) {}

    /**
     * @throws WorkoutServiceException
     */
    public function updateWorkingSet(Workout $workout, WorkoutSet $set, CompleteWorkoutSetData $data): ?ProgressionSessionData
    {
        $set->assertBelongsToWorkout($workout);
        $set->loadMissing('segments');

        if ($workout->status !== WorkoutStatus::Finished) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_FINISHED_ERROR);
        }

        if ($set->setGroup->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(self::WARM_UP_SETS_READ_ONLY_ERROR);
        }

        DB::transaction(function () use ($set, $data): void {
            if ($set->isDropset() || $data->segments !== null) {
                $this->updateDropset($set, $data);
            } else {
                $this->updateSingleSet($set, $data);
            }

            if ($set->completed_at === null) {
                $set->completed_at = now();
            }

            $set->save();
        });

        if (! $workout->isEligibleForProgressionReEval()) {
            return null;
        }

        $session = $this->progressionService->reEvaluateProgression($workout, collectNewBumps: false);

        if ($session->hasActions()) {
            $this->progressionService->storeProgressionSession($workout, $session);
        }

        return $session->hasActions() ? $session : null;
    }

    /**
     * @throws WorkoutServiceException
     */
    private function updateDropset(WorkoutSet $set, CompleteWorkoutSetData $data): void
    {
        $segmentWeights = $data->segmentWeightGrams();

        if ($segmentWeights === null || count($segmentWeights) < 2) {
            throw new WorkoutServiceException(WorkoutService::DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        $set->replaceSegments($segmentWeights);

        $set->reps = $data->reps;
        $set->weight_g = null;
        $set->plate_stack = null;
    }

    /**
     * @throws WorkoutServiceException
     */
    private function updateSingleSet(WorkoutSet $set, CompleteWorkoutSetData $data): void
    {
        $weightGrams = $data->weightGrams();

        if ($weightGrams === null) {
            throw new WorkoutServiceException(WorkoutService::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
        }

        $set->replaceSegments([]);
        $set->reps = $data->reps;
        $set->weight_g = $weightGrams;
        $set->plate_stack = null;
    }
}
