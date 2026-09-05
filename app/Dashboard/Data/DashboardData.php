<?php

namespace App\Dashboard\Data;

use App\Routines\Data\RoutineData;
use App\Routines\Models\Routine;
use App\Users\Models\User;
use App\Workouts\Data\History\HistoryWorkoutItemData;
use App\Workouts\Data\InProgressWorkoutData;
use App\Workouts\Models\Workout;
use App\Workouts\Services\StandardsSinceDeloadCounter;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class DashboardData extends Data
{
    /**
     * @param  Collection<int, RoutineData>  $routines
     * @param  Collection<int, HistoryWorkoutItemData>  $recentFinishedWorkouts
     */
    public function __construct(
        public readonly Collection $routines,
        public readonly ?InProgressWorkoutData $inProgressWorkout,
        public readonly Collection $recentFinishedWorkouts,
    ) {}

    public static function fromUser(User $user, StandardsSinceDeloadCounter $standardsSinceDeloadCounter): DashboardData
    {
        $user->loadMissing(['routines.blocks.blockExercises']);

        $inProgress = $user->workouts()
            ->with(['routine', 'blocks.setGroups.sets'])
            ->inProgress()
            ->latest('started_at')
            ->first();

        $recentFinished = $user->workouts()
            ->with('routine')
            ->finished()
            ->orderByDesc('finished_at')
            ->limit(3)
            ->get()
            ->map(fn (Workout $workout): HistoryWorkoutItemData => HistoryWorkoutItemData::fromWorkout($workout));

        $standardsSinceDeload = $standardsSinceDeloadCounter->summarizeByRoutineId($user, $user->routines->pluck('id'));

        $parkedIncompleteCount = 0;
        if ($inProgress !== null) {
            $parkedIncompleteCount = $inProgress->blocks
                ->filter(fn ($block): bool => $block->is_parked)
                ->filter(fn ($block): bool => $block->setGroups
                    ->flatMap(fn ($group) => $group->sets)
                    ->contains(fn ($set): bool => $set->completed_at === null))
                ->count();
        }

        return new self(
            routines: $user->routines->map(function (Routine $routine) use ($standardsSinceDeload): RoutineData {
                $summary = $standardsSinceDeload[$routine->id] ?? ['count' => 0, 'has_finished_deload' => false];

                return RoutineData::fromRoutine(
                    $routine,
                    $summary['count'],
                    $summary['has_finished_deload'],
                );
            }),
            inProgressWorkout: $inProgress === null ? null : new InProgressWorkoutData(
                id: $inProgress->ulid,
                routineName: $inProgress->routine->getName(),
                mode: $inProgress->mode->value,
                parkedIncompleteCount: $parkedIncompleteCount,
            ),
            recentFinishedWorkouts: $recentFinished,
        );
    }
}
