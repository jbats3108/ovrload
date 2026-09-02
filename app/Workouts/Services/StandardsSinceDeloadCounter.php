<?php

namespace App\Workouts\Services;

use App\Users\Models\User;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Models\Workout;
use Illuminate\Support\Collection;

final class StandardsSinceDeloadCounter
{
    /**
     * Finished standard workouts per routine since that routine's latest finished deload.
     * If a routine has never had a finished deload, counts all of its finished standards
     * and sets has_finished_deload to false (UI should not say "since deload").
     *
     * @param  Collection<int, int>|list<int>  $routineIds
     * @return array<int, array{count: int, has_finished_deload: bool}>
     */
    public function summarizeByRoutineId(User $user, Collection|array $routineIds): array
    {
        $ids = Collection::wrap($routineIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        /** @var array<int, array{count: int, has_finished_deload: bool}> $summaries */
        $summaries = [];
        foreach ($ids as $id) {
            $summaries[$id] = ['count' => 0, 'has_finished_deload' => false];
        }

        $workouts = Workout::query()
            ->where('user_id', $user->id)
            ->finished()
            ->whereIn('routine_id', $ids)
            ->whereIn('mode', [WorkoutMode::Standard, WorkoutMode::Deload])
            ->orderBy('finished_at')
            ->orderBy('id')
            ->get(['routine_id', 'mode', 'finished_at']);

        $lastDeloadAt = [];
        foreach ($workouts as $workout) {
            $routineId = $workout->routine_id;
            if (! array_key_exists($routineId, $summaries)) {
                continue;
            }

            if ($workout->mode === WorkoutMode::Deload && $workout->finished_at !== null) {
                $lastDeloadAt[$routineId] = $workout->finished_at;
                $summaries[$routineId]['has_finished_deload'] = true;
            }
        }

        foreach ($workouts as $workout) {
            $routineId = $workout->routine_id;
            if (
                $workout->mode !== WorkoutMode::Standard
                || $workout->finished_at === null
                || ! array_key_exists($routineId, $summaries)
            ) {
                continue;
            }

            $cutoff = $lastDeloadAt[$routineId] ?? null;
            if ($cutoff !== null && $workout->finished_at->lte($cutoff)) {
                continue;
            }

            $summaries[$routineId]['count']++;
        }

        return $summaries;
    }
}
