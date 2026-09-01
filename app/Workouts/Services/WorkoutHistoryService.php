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
        private readonly WorkoutSetLogger $setLogger,
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
                $this->setLogger->applyLoggedValues(
                    $set,
                    $data->reps,
                    segmentWeightGrams: $data->segmentWeightGrams(),
                );
            } else {
                $this->setLogger->applyLoggedValues(
                    $set,
                    $data->reps,
                    weightGrams: $data->weightGrams(),
                    plateStack: null,
                );
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
}
