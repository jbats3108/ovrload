<?php

namespace App\Workouts\Services;

use App\Shared\Enums\SetGroupType;
use App\Workouts\Data\History\UpdateHistoryWorkingSetData;
use App\Workouts\Data\History\UpdateWorkoutHistoryData;
use App\Workouts\Data\Progression\ProgressionSessionData;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutSet;
use Illuminate\Support\Facades\DB;

final readonly class WorkoutHistoryService
{
    public const string WORKOUT_NOT_FINISHED_ERROR = 'Only finished workouts can be edited in history';

    public const string WARM_UP_SETS_READ_ONLY_ERROR = 'Warm-up sets cannot be edited in history';

    public const string DUPLICATE_SET_IDS_ERROR = 'Each set can only appear once in a history save';

    public function __construct(
        private WorkoutProgressionService $progressionService,
        private WorkoutSetLogger $setLogger,
    ) {}

    /**
     * @throws WorkoutServiceException
     */
    public function updateWorkingSets(Workout $workout, UpdateWorkoutHistoryData $data): ?ProgressionSessionData
    {
        if ($workout->status !== WorkoutStatus::Finished) {
            throw new WorkoutServiceException(self::WORKOUT_NOT_FINISHED_ERROR);
        }

        $setIds = $data->sets->toCollection()->map(fn (UpdateHistoryWorkingSetData $set): int => $set->id)->all();

        if (count($setIds) !== count(array_unique($setIds))) {
            throw new WorkoutServiceException(self::DUPLICATE_SET_IDS_ERROR);
        }

        /** @var list<UpdateHistoryWorkingSetData> $updates */
        $updates = $data->sets->all();

        DB::transaction(function () use ($workout, $setIds, $updates): void {
            $setsById = WorkoutSet::query()
                ->whereIn('id', $setIds)
                ->with(['segments', 'setGroup.block'])
                ->get()
                ->keyBy('id');

            foreach ($updates as $update) {
                $set = $setsById->get($update->id);
                abort_unless($set instanceof WorkoutSet, 404);
                $set->assertBelongsToWorkout($workout);
                $this->applyWorkingSetUpdate($set, $update);
            }
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
    private function applyWorkingSetUpdate(WorkoutSet $set, UpdateHistoryWorkingSetData $data): void
    {
        $set->loadMissing('segments', 'setGroup');

        if ($set->setGroup->type !== SetGroupType::Working) {
            throw new WorkoutServiceException(self::WARM_UP_SETS_READ_ONLY_ERROR);
        }

        if ($data->segments !== null) {
            $this->setLogger->applyLoggedValues(
                $set,
                $data->reps,
                segmentWeightGrams: $data->segmentWeightGrams(),
            );
        } elseif ($set->isDropset()) {
            $set->reps = $data->reps;
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
    }
}
