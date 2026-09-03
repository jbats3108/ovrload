import { formatRest } from '@/routines/lib/formatRest';
import { formatWarmUpStep, warmUpText } from '@/routines/lib/warmUp';
import type { Block, BlockExercise } from '@/routines/types';

/** Target / Floor / deload alt are editable when the exercise is Custom (no profile). */
export function exerciseRecipeIsCustom(exercise: BlockExercise): boolean {
    return exercise.exercise_profile_id == null;
}

/** Working rest / warm-up are editable when the block has no shared profile. */
export function blockSharedRecipeIsCustom(block: Block): boolean {
    return block.shared_profile_id == null;
}

export function formatExerciseTargetFloorSummary(exercise: BlockExercise, floorPlaceholder: string): string {
    const floor =
        exercise.achievement_floor != null && Number.isFinite(exercise.achievement_floor) ? String(exercise.achievement_floor) : floorPlaceholder;

    return `Target ${exercise.prescribed_reps} · Floor ${floor}`;
}

export function formatBlockRestSummary(block: Block): string {
    return formatRest(block.working.rest_seconds);
}

export function formatBlockWarmUpSummary(block: Block): { steps: string[]; rest: string | null } {
    if (!block.warm_up.steps.length) {
        return { steps: ['No warm-up'], rest: null };
    }

    return {
        steps: block.warm_up.steps.map((step) => formatWarmUpStep(step)),
        rest: formatRest(block.warm_up.rest_seconds),
    };
}

/** Mobile / combined summary line. */
export function formatBlockSharedRecipeSummary(block: Block): string {
    const rest = `Rest ${formatBlockRestSummary(block)}`;
    const warmUp = block.warm_up.steps.length ? warmUpText(block) : 'No warm-up';
    const wuRest = block.warm_up.steps.length ? ` · WU rest ${formatRest(block.warm_up.rest_seconds)}` : '';

    return `${rest} · ${warmUp}${wuRest}`;
}
