import { formatRest } from '@/routines/lib/formatRest';
import { formatWarmUpStep, warmUpText } from '@/routines/lib/warmUp';
import type { Block, BlockExercise } from '@/routines/types';

/** Target / Floor are editable when the exercise is Custom (no profile). Deload alt is always available. */
export function exerciseRecipeIsCustom(exercise: BlockExercise): boolean {
    return exercise.exercise_profile_id == null;
}

/** Working rest / warm-up are editable when the block has no shared profile. */
export function blockSharedRecipeIsCustom(block: Block): boolean {
    return block.shared_profile_id == null;
}

export function formatExerciseTargetFloorSummary(exercise: BlockExercise, floorPlaceholder: string): string {
    if (exercise.prescription_mode === 'duration') {
        return `Duration ${exercise.prescribed_duration_seconds ?? 30}s`;
    }

    const floor =
        exercise.achievement_floor != null && Number.isFinite(exercise.achievement_floor) ? String(exercise.achievement_floor) : floorPlaceholder;

    return `Target ${exercise.prescribed_reps ?? 6} · Floor ${floor}`;
}

export function formatBlockRestSummary(block: Block): string {
    if (block.type === 'circuit') {
        return `Station ${formatRest(block.stage_rest_seconds ?? 15)} · Round ${formatRest(block.working.rest_seconds)}`;
    }
    return formatRest(block.working.rest_seconds);
}

export function formatBlockWarmUpSummary(block: Block): { steps: string[]; rest: string | null } {
    if (block.type === 'circuit' || !block.warm_up.steps.length) {
        return { steps: ['No warm-up'], rest: null };
    }

    return {
        steps: block.warm_up.steps.map((step) => formatWarmUpStep(step)),
        rest: formatRest(block.warm_up.rest_seconds),
    };
}

/** Mobile / combined summary line. */
export function formatBlockSharedRecipeSummary(block: Block): string {
    if (block.type === 'circuit') {
        return `Station ${formatRest(block.stage_rest_seconds ?? 15)} · Round ${formatRest(block.working.rest_seconds)}`;
    }
    const rest = `Rest ${formatBlockRestSummary(block)}`;
    const warmUp = block.warm_up.steps.length ? warmUpText(block) : 'No warm-up';
    const wuRest = block.warm_up.steps.length ? ` · WU rest ${formatRest(block.warm_up.rest_seconds)}` : '';

    return `${rest} · ${warmUp}${wuRest}`;
}
