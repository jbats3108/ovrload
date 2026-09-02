<?php

namespace App\Workouts\Services;

use App\Routines\Models\RoutineBlockExercise;
use App\Shared\Enums\SetGroupType;
use App\Users\Enums\ProgressionStyle;
use App\Workouts\Data\Progression\BumpProposalData;
use App\Workouts\Data\Progression\ProgressionSessionData;
use App\Workouts\Data\Progression\UndoBumpProposalData;
use App\Workouts\Enums\WorkoutMode;
use App\Workouts\Models\BumpRecord;
use App\Workouts\Models\Workout;
use App\Workouts\Models\WorkoutBlockExercise;
use App\Workouts\Models\WorkoutSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

final class WorkoutProgressionService
{
    /** Default bump when no plate calculator step is available yet (2.5 kg). */
    public const int DEFAULT_BUMP_G = 2500;

    /** @return DataCollection<int, BumpProposalData> */
    public function applyCarryForwardAndCollectBumps(Workout $workout): DataCollection
    {
        return $this->reEvaluateProgression($workout, collectNewBumps: true)->bumps;
    }

    public function reEvaluateProgression(Workout $workout, bool $collectNewBumps = true): ProgressionSessionData
    {
        if ($workout->mode === WorkoutMode::Deload) {
            return new ProgressionSessionData(
                bumps: BumpProposalData::collect([], DataCollection::class),
                undos: UndoBumpProposalData::collect([], DataCollection::class),
            );
        }

        $workout->load([
            'routine.blocks.blockExercises',
            'blocks.blockExercises.sets.setGroup',
            'blocks.blockExercises.sets.segments',
            'bumpRecords',
        ]);

        $activeBumpExerciseIds = $workout->bumpRecords
            ->filter(fn (BumpRecord $record): bool => $record->isActive())
            ->pluck('routine_block_exercise_id')
            ->all();

        $bumps = [];
        $exerciseNamesByRoutineId = [];

        foreach ($workout->blocks as $workoutBlock) {
            if ($workoutBlock->is_ad_hoc) {
                continue;
            }

            $routineBlock = $workout->routine->blocks->firstWhere('position', $workoutBlock->position);

            if ($routineBlock === null) {
                continue;
            }

            foreach ($workoutBlock->blockExercises as $workoutExercise) {
                $routineExercise = $this->matchRoutineExercise($routineBlock->blockExercises, $workoutExercise);

                if ($routineExercise === null) {
                    continue;
                }

                $exerciseNamesByRoutineId[$routineExercise->id] = $workoutExercise->exercise_name;
                $workingSets = $this->completedWorkingSets($workoutExercise);

                $this->carryForward($routineExercise, $workoutExercise, $workingSets);

                if (
                    $collectNewBumps
                    && $this->hitProgressionTarget($workoutExercise, $workingSets, $workout)
                    && ! in_array($routineExercise->id, $activeBumpExerciseIds, true)
                ) {
                    $routineExercise->refresh();
                    $from = $routineExercise->working_weight_g;
                    $bumps[] = new BumpProposalData(
                        routineBlockExerciseId: $routineExercise->id,
                        exerciseName: $workoutExercise->exercise_name,
                        fromWeightG: $from,
                        toWeightG: $from + self::DEFAULT_BUMP_G,
                    );
                }
            }
        }

        $undos = $this->collectUndoProposals($workout, $exerciseNamesByRoutineId);

        return new ProgressionSessionData(
            bumps: BumpProposalData::collect($bumps, DataCollection::class),
            undos: UndoBumpProposalData::collect($undos, DataCollection::class),
        );
    }

    /**
     * @param  list<int>  $routineBlockExerciseIds
     * @param  DataCollection<int, BumpProposalData>  $proposals
     */
    public function applyConfirmedBumps(Workout $workout, DataCollection $proposals, array $routineBlockExerciseIds): void
    {
        $selected = collect($routineBlockExerciseIds)->unique()->all();

        DB::transaction(function () use ($workout, $proposals, $selected): void {
            foreach ($proposals as $proposal) {
                /** @var BumpProposalData $proposal */
                if (! in_array($proposal->routineBlockExerciseId, $selected, true)) {
                    continue;
                }

                $exercise = RoutineBlockExercise::query()->find($proposal->routineBlockExerciseId);

                if ($exercise === null) {
                    continue;
                }

                // Only raise when the routine weight still matches the proposal base.
                // Avoid lowering past intervening carry-forward, later bumps, or manual edits.
                if ($exercise->working_weight_g !== $proposal->fromWeightG) {
                    continue;
                }

                $exercise->working_weight_g = $proposal->toWeightG;
                $exercise->save();

                BumpRecord::query()->updateOrCreate(
                    [
                        'workout_id' => $workout->id,
                        'routine_block_exercise_id' => $proposal->routineBlockExerciseId,
                    ],
                    [
                        'from_weight_g' => $proposal->fromWeightG,
                        'to_weight_g' => $proposal->toWeightG,
                        'undone_at' => null,
                    ],
                );
            }
        });
    }

    /**
     * @param  list<int>  $bumpRecordIds
     */
    public function applyConfirmedUndos(Workout $workout, array $bumpRecordIds): void
    {
        $selected = collect($bumpRecordIds)->unique()->all();

        if ($selected === []) {
            return;
        }

        DB::transaction(function () use ($workout, $selected): void {
            $records = BumpRecord::query()
                ->where('workout_id', $workout->id)
                ->whereNull('undone_at')
                ->whereIn('id', $selected)
                ->with('routineBlockExercise')
                ->get();

            foreach ($records as $record) {
                $exercise = $record->routineBlockExercise;

                if ($exercise === null) {
                    continue;
                }

                // Only revert when the routine still sits at the bumped weight.
                if ($exercise->working_weight_g !== $record->to_weight_g) {
                    continue;
                }

                $exercise->working_weight_g = $record->from_weight_g;
                $exercise->save();

                $record->undone_at = now();
                $record->save();
            }
        });
    }

    public function storeProgressionSession(Workout $workout, ProgressionSessionData $session): void
    {
        $this->forgetSiblingProgressionSessions($workout);

        session([
            "workout_progression.{$workout->id}" => $session->bumps->toArray(),
            "workout_progression_undos.{$workout->id}" => $session->undos->toArray(),
        ]);
    }

    /**
     * Drop leftover progression sessions for older finishes of the same routine.
     */
    public function forgetSiblingProgressionSessions(Workout $workout): void
    {
        $siblingIds = Workout::query()
            ->where('user_id', $workout->user_id)
            ->where('routine_id', $workout->routine_id)
            ->whereKeyNot($workout->id)
            ->pluck('id');

        foreach ($siblingIds as $siblingId) {
            session()->forget([
                "workout_progression.{$siblingId}",
                "workout_progression_undos.{$siblingId}",
            ]);
        }
    }

    public function pullProgressionSession(Workout $workout): ?ProgressionSessionData
    {
        $storedBumps = session()->pull("workout_progression.{$workout->id}");
        $storedUndos = session()->pull("workout_progression_undos.{$workout->id}");

        $hasBumps = is_array($storedBumps) && $storedBumps !== [];
        $hasUndos = is_array($storedUndos) && $storedUndos !== [];

        if (! $hasBumps && ! $hasUndos) {
            return null;
        }

        return new ProgressionSessionData(
            bumps: BumpProposalData::collect($hasBumps ? $storedBumps : [], DataCollection::class),
            undos: UndoBumpProposalData::collect($hasUndos ? $storedUndos : [], DataCollection::class),
        );
    }

    public function forgetProgressionSession(Workout $workout): void
    {
        session()->forget([
            "workout_progression.{$workout->id}",
            "workout_progression_undos.{$workout->id}",
        ]);
    }

    public function hasProgressionSession(Workout $workout): bool
    {
        $storedBumps = session("workout_progression.{$workout->id}");
        $storedUndos = session("workout_progression_undos.{$workout->id}");

        return (is_array($storedBumps) && $storedBumps !== [])
            || (is_array($storedUndos) && $storedUndos !== []);
    }

    /**
     * @param  array<int, string>  $exerciseNamesByRoutineId
     * @return list<UndoBumpProposalData>
     */
    private function collectUndoProposals(Workout $workout, array $exerciseNamesByRoutineId): array
    {
        $undos = [];

        foreach ($workout->bumpRecords->filter(fn (BumpRecord $record): bool => $record->isActive()) as $record) {
            $workoutExercise = $this->findWorkoutExerciseForRoutineExercise($workout, $record->routine_block_exercise_id);

            if ($workoutExercise === null) {
                continue;
            }

            $routineExercise = RoutineBlockExercise::query()->find($record->routine_block_exercise_id);

            if ($routineExercise === null || $routineExercise->working_weight_g !== $record->to_weight_g) {
                continue;
            }

            $workingSets = $this->completedWorkingSets($workoutExercise);

            if ($this->hitProgressionTarget($workoutExercise, $workingSets, $workout)) {
                continue;
            }

            $undos[] = new UndoBumpProposalData(
                bumpRecordId: $record->id,
                routineBlockExerciseId: $record->routine_block_exercise_id,
                exerciseName: $exerciseNamesByRoutineId[$record->routine_block_exercise_id] ?? 'Exercise',
                fromWeightG: $record->to_weight_g,
                toWeightG: $record->from_weight_g,
            );
        }

        return $undos;
    }

    private function findWorkoutExerciseForRoutineExercise(Workout $workout, int $routineBlockExerciseId): ?WorkoutBlockExercise
    {
        $routineExercise = RoutineBlockExercise::query()->find($routineBlockExerciseId);

        if ($routineExercise === null) {
            return null;
        }

        foreach ($workout->blocks as $workoutBlock) {
            $routineBlock = $workout->routine->blocks->firstWhere('position', $workoutBlock->position);

            if ($routineBlock === null || $routineBlock->id !== $routineExercise->routine_block_id) {
                continue;
            }

            return $workoutBlock->blockExercises
                ->first(fn (WorkoutBlockExercise $exercise): bool => $exercise->exercise_id === $routineExercise->exercise_id
                    || $exercise->position === $routineExercise->position);
        }

        return null;
    }

    /**
     * @param  Collection<int, RoutineBlockExercise>  $routineExercises
     */
    private function matchRoutineExercise(Collection $routineExercises, WorkoutBlockExercise $workoutExercise): ?RoutineBlockExercise
    {
        return $routineExercises->firstWhere('exercise_id', $workoutExercise->exercise_id)
            ?? $routineExercises->firstWhere('position', $workoutExercise->position);
    }

    /**
     * @return Collection<int, WorkoutSet>
     */
    private function completedWorkingSets(WorkoutBlockExercise $workoutExercise): Collection
    {
        return $workoutExercise->sets
            ->filter(fn (WorkoutSet $set): bool => $set->completed_at !== null
                && $set->setGroup?->type === SetGroupType::Working
                && $set->reps !== null
                && $set->weight_g !== null
                && ! $set->isDropset())
            ->values();
    }

    /**
     * @param  Collection<int, WorkoutSet>  $workingSets
     */
    private function carryForward(
        RoutineBlockExercise $routineExercise,
        WorkoutBlockExercise $workoutExercise,
        Collection $workingSets,
    ): void {
        $floor = $workoutExercise->achievement_floor ?? 1;

        $highestAchieved = $workingSets
            ->filter(fn (WorkoutSet $set): bool => $set->reps >= $floor)
            ->max('weight_g');

        if ($highestAchieved === null) {
            return;
        }

        if ($highestAchieved > $routineExercise->working_weight_g) {
            $routineExercise->working_weight_g = (int) $highestAchieved;
            $routineExercise->save();
        }
    }

    /**
     * @param  Collection<int, WorkoutSet>  $workingSets
     */
    private function hitProgressionTarget(WorkoutBlockExercise $workoutExercise, Collection $workingSets, Workout $workout): bool
    {
        $target = $workoutExercise->prescribed_reps;

        $style = $workout->progression_style ?? ProgressionStyle::StraightSets;

        if ($style === ProgressionStyle::ProgressiveOverload) {
            $decisive = $this->lastSetAtTopWeight($workingSets);
            $lastWorking = $this->lastWorkingSet($workingSets);

            return $decisive !== null
                && $lastWorking !== null
                && $decisive->is($lastWorking)
                && $decisive->weight_g >= $workoutExercise->working_weight_g
                && $decisive->reps >= $target;
        }

        return $workingSets->contains(
            fn (WorkoutSet $set): bool => $set->weight_g >= $workoutExercise->working_weight_g
                && $set->reps >= $target
        );
    }

    /**
     * Chronologically last completed working set in the block.
     *
     * @param  Collection<int, WorkoutSet>  $workingSets
     */
    private function lastWorkingSet(Collection $workingSets): ?WorkoutSet
    {
        if ($workingSets->isEmpty()) {
            return null;
        }

        return $workingSets
            ->sortBy([
                ['set_index', 'asc'],
                ['id', 'asc'],
            ])
            ->last();
    }

    /**
     * Among completed working sets, the chronologically last set at the session's heaviest weight.
     *
     * @param  Collection<int, WorkoutSet>  $workingSets
     */
    private function lastSetAtTopWeight(Collection $workingSets): ?WorkoutSet
    {
        if ($workingSets->isEmpty()) {
            return null;
        }

        $topWeight = $workingSets->max('weight_g');

        return $workingSets
            ->filter(fn (WorkoutSet $set): bool => $set->weight_g === $topWeight)
            ->sortBy([
                ['set_index', 'asc'],
                ['id', 'asc'],
            ])
            ->last();
    }
}
