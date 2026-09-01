<?php

namespace App\Workouts\Services;

use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\WorkoutSet;
use Carbon\CarbonInterface;

final readonly class WorkoutSetLogger
{
    /**
     * @param  list<int>|null  $segmentWeightGrams
     * @param  array{bar_g: int, per_side: list<array{denomination_g: int, count: int}>}|null  $plateStack
     *
     * @throws WorkoutServiceException
     */
    public function applyLoggedValues(
        WorkoutSet $set,
        int $reps,
        ?int $weightGrams = null,
        ?array $segmentWeightGrams = null,
        ?array $plateStack = null,
        ?CarbonInterface $completedAt = null,
    ): void {
        $hasSegments = $segmentWeightGrams !== null && count($segmentWeightGrams) >= 2;

        if ($hasSegments) {
            if (count($segmentWeightGrams) < 2) {
                throw new WorkoutServiceException(WorkoutService::DROPSET_REQUIRES_SEGMENTS_ERROR);
            }

            $set->replaceSegments($segmentWeightGrams);
            $set->reps = $reps;
            $set->weight_g = null;
            $set->plate_stack = null;
        } else {
            if ($weightGrams === null) {
                throw new WorkoutServiceException(WorkoutService::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
            }

            $set->replaceSegments([]);
            $set->reps = $reps;
            $set->weight_g = $weightGrams;
            $set->plate_stack = $plateStack;
        }

        if ($completedAt !== null) {
            $set->completed_at = $completedAt;
        }
    }
}
