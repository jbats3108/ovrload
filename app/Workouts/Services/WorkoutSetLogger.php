<?php

namespace App\Workouts\Services;

use App\Workouts\Exceptions\WorkoutServiceException;
use App\Workouts\Models\WorkoutSet;
use Carbon\Carbon;
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
        ?int $reps = null,
        ?int $weightGrams = null,
        ?array $segmentWeightGrams = null,
        ?array $plateStack = null,
        ?CarbonInterface $completedAt = null,
        bool $deleteExistingSegments = true,
        ?int $durationSeconds = null,
        bool $isSkipped = false,
    ): void {
        if ($isSkipped) {
            $set->replaceSegments([], $deleteExistingSegments);
            $set->reps = null;
            $set->duration_seconds = null;
            $set->weight_g = null;
            $set->plate_stack = null;
            $set->is_skipped = true;
            $set->completed_at = Carbon::instance($completedAt ?? now());

            return;
        }

        $set->is_skipped = false;

        $hasSegments = $segmentWeightGrams !== null && count($segmentWeightGrams) >= 2;

        if ($hasSegments) {
            if (count($segmentWeightGrams) < 2) {
                throw new WorkoutServiceException(WorkoutService::DROPSET_REQUIRES_SEGMENTS_ERROR);
            }

            $set->replaceSegments($segmentWeightGrams, $deleteExistingSegments);
            $set->reps = $reps;
            $set->duration_seconds = null;
            $set->weight_g = null;
            $set->plate_stack = null;
        } else {
            if ($weightGrams === null && $set->isDropset()) {
                throw new WorkoutServiceException(WorkoutService::PLANNED_DROPSET_REQUIRES_SEGMENTS_ERROR);
            }

            $set->replaceSegments([], $deleteExistingSegments);
            $set->reps = $durationSeconds !== null ? null : $reps;
            $set->duration_seconds = $durationSeconds;
            $set->weight_g = $weightGrams ?? 0;
            $set->plate_stack = $plateStack;
        }

        $set->completed_at = $completedAt !== null ? Carbon::instance($completedAt) : now();
    }
}
