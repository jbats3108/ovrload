<?php

declare(strict_types=1);

namespace App\Routines\Services;

use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Users\Models\User;
use Illuminate\Support\Facades\DB;

class RoutineDuplicator
{
    public function duplicate(Routine $source, User $owner): Routine
    {
        return DB::transaction(function () use ($source, $owner): Routine {
            // Serialize duplicates per owner so concurrent POSTs cannot interleave slug/create work.
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            $source->loadMissing([
                'blocks.blockExercises',
                'blocks.setGroups.warmUpSteps',
                'blocks.setGroups.dropsetSegments',
            ]);

            $copy = Routine::create([
                'user_id' => $owner->id,
                'name' => $this->copyName($source->name),
                'deload_weight_factor' => $source->deload_weight_factor,
                'deload_reps_factor' => $source->deload_reps_factor,
                'deload_every_n' => $source->deload_every_n,
                'default_exercise_profile_id' => $source->default_exercise_profile_id,
            ]);

            foreach ($source->blocks as $block) {
                $this->copyBlock($copy, $block);
            }

            return $copy->fresh([
                'blocks.blockExercises.exercise',
                'blocks.setGroups.warmUpSteps',
                'blocks.setGroups.dropsetSegments',
            ]) ?? $copy;
        });
    }

    private function copyName(string $name): string
    {
        $base = trim($name);
        if ($base === '') {
            return 'Routine (copy)';
        }

        if (str_ends_with(mb_strtolower($base), '(copy)')) {
            return $base;
        }

        return $base.' (copy)';
    }

    private function copyBlock(Routine $copy, RoutineBlock $block): void
    {
        $newBlock = RoutineBlock::create([
            'routine_id' => $copy->id,
            'shared_exercise_profile_id' => $block->shared_exercise_profile_id,
            'shared_profile_fingerprint' => $block->shared_profile_fingerprint,
            'position' => $block->position,
            'is_superset' => $block->is_superset,
            'has_setup_after' => $block->has_setup_after,
            'has_setup_after_warm_up' => $block->has_setup_after_warm_up,
        ]);

        foreach ($block->blockExercises as $blockExercise) {
            Exercise::assertAvailableFor($copy->user, $blockExercise->exercise_id);

            if ($blockExercise->deload_exercise_id !== null) {
                Exercise::assertAvailableFor($copy->user, $blockExercise->deload_exercise_id);
            }

            RoutineBlockExercise::create([
                'routine_block_id' => $newBlock->id,
                'exercise_profile_id' => $blockExercise->exercise_profile_id,
                'exercise_profile_fingerprint' => $blockExercise->exercise_profile_fingerprint,
                'exercise_id' => $blockExercise->exercise_id,
                'position' => $blockExercise->position,
                'working_weight_g' => $blockExercise->working_weight_g,
                'deload_exercise_id' => $blockExercise->deload_exercise_id,
                'deload_working_weight_g' => $blockExercise->deload_working_weight_g,
                'prescribed_reps' => $blockExercise->prescribed_reps,
                'achievement_floor_override' => $blockExercise->achievement_floor_override,
                'floor_is_derived' => $blockExercise->floor_is_derived,
                'progression_target_override' => $blockExercise->progression_target_override,
            ]);
        }

        foreach ($block->setGroups as $setGroup) {
            $this->copySetGroup($newBlock, $setGroup);
        }
    }

    private function copySetGroup(RoutineBlock $newBlock, RoutineSetGroup $setGroup): void
    {
        $newSetGroup = RoutineSetGroup::create([
            'routine_block_id' => $newBlock->id,
            'type' => $setGroup->type,
            'set_count' => $setGroup->set_count,
            'rest_seconds' => $setGroup->rest_seconds,
        ]);

        foreach ($setGroup->warmUpSteps as $step) {
            RoutineWarmUpStep::create([
                'routine_set_group_id' => $newSetGroup->id,
                'position' => $step->position,
                'weight_mode' => $step->weight_mode,
                'percent_of_working' => $step->percent_of_working,
                'reps' => $step->reps,
                'has_setup_after' => $step->has_setup_after,
            ]);
        }

        foreach ($setGroup->dropsetSegments as $segment) {
            RoutineDropsetSegment::create([
                'routine_set_group_id' => $newSetGroup->id,
                'set_index' => $segment->set_index,
                'position' => $segment->position,
                'weight_g' => $segment->weight_g,
            ]);
        }
    }
}
