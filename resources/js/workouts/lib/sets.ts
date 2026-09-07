import type { FlatSetEntry } from '@/workouts/lib/focus';
import type { PlayerBlock, PlayerSet } from '@/workouts/types';

export function previousSetWeightKg(entry: FlatSetEntry): number | null {
    const prior = entry.block.sets
        .filter(
            (s) =>
                s.workout_block_exercise_id === entry.set.workout_block_exercise_id &&
                s.group_type === entry.set.group_type &&
                s.set_index < entry.set.set_index &&
                s.completed &&
                s.logged_weight_kg != null,
        )
        .sort((a, b) => b.set_index - a.set_index)[0];

    return prior?.logged_weight_kg ?? null;
}

export function workingWeightForSet(entry: FlatSetEntry): number {
    const exercise = entry.block.exercises.find((e) => e.id === entry.set.workout_block_exercise_id);
    return exercise?.working_weight_kg ?? entry.set.target_weight_kg ?? 0;
}

export function isCircuit(block: PlayerBlock): boolean {
    return block.type === 'circuit';
}

export function shouldRestAfter(block: PlayerBlock, set: PlayerSet): boolean {
    if (block.type === 'circuit') {
        return true;
    }
    if (!block.is_superset) {
        return true;
    }
    const sameIndex = block.sets.filter((s) => s.set_index === set.set_index && s.group_type === set.group_type);
    return sameIndex.every((s) => s.completed || s.id === set.id);
}

function exercisePositionInBlock(block: PlayerBlock, workoutBlockExerciseId: number): number {
    return block.exercises.find((exercise) => exercise.id === workoutBlockExerciseId)?.position ?? 0;
}

/** Sets in the same group round, ordered by exercise position (A then B for supersets). */
export function supersetRoundSets(block: PlayerBlock, set: PlayerSet): PlayerSet[] {
    if (!block.is_superset) {
        return [set];
    }

    return block.sets
        .filter((candidate) => candidate.group_type === set.group_type && candidate.set_index === set.set_index)
        .sort((a, b) => exercisePositionInBlock(block, a.workout_block_exercise_id) - exercisePositionInBlock(block, b.workout_block_exercise_id));
}

/** Sets in the same circuit round, ordered by exercise position. */
export function circuitRoundSets(block: PlayerBlock, set: PlayerSet): PlayerSet[] {
    if (block.type !== 'circuit') {
        return [set];
    }

    return block.sets
        .filter((candidate) => candidate.group_type === set.group_type && candidate.set_index === set.set_index)
        .sort((a, b) => exercisePositionInBlock(block, a.workout_block_exercise_id) - exercisePositionInBlock(block, b.workout_block_exercise_id));
}

/** Next exercise still to play in this circuit round, or null on the last exercise. */
export function nextCircuitSet(block: PlayerBlock, set: PlayerSet): PlayerSet | null {
    if (block.type !== 'circuit') {
        return null;
    }

    const round = circuitRoundSets(block, set);
    const index = round.findIndex((candidate) => candidate.id === set.id);
    if (index < 0 || index >= round.length - 1) {
        return null;
    }

    return round[index + 1] ?? null;
}

export function isLastInCircuitRound(block: PlayerBlock, set: PlayerSet): boolean {
    if (block.type !== 'circuit') {
        return false;
    }
    const round = circuitRoundSets(block, set);
    return round.length > 0 && round[round.length - 1]?.id === set.id;
}

/** Next exercise still to play in this superset round (A → B), or null on the last of the pair. */
export function nextSupersetSet(block: PlayerBlock, set: PlayerSet): PlayerSet | null {
    if (!block.is_superset) {
        return null;
    }

    const round = supersetRoundSets(block, set);
    const index = round.findIndex((candidate) => candidate.id === set.id);
    if (index < 0 || index >= round.length - 1) {
        return null;
    }

    return round[index + 1] ?? null;
}

export function finishesWarmUpGroup(block: PlayerBlock, set: PlayerSet): boolean {
    if (set.group_type !== 'warm_up') {
        return false;
    }
    return block.sets.filter((s) => s.group_type === 'warm_up').every((s) => s.completed || s.id === set.id);
}

export function warmUpStepIndexes(block: PlayerBlock): number[] {
    const indexes = new Set(block.sets.filter((s) => s.group_type === 'warm_up').map((s) => s.set_index));

    return [...indexes].sort((a, b) => a - b);
}

export function finishesWarmUpStep(block: PlayerBlock, set: PlayerSet): boolean {
    if (set.group_type !== 'warm_up' || !set.has_setup_after) {
        return false;
    }

    const stepIndexes = warmUpStepIndexes(block);
    const lastStepIndex = stepIndexes[stepIndexes.length - 1];
    if (set.set_index === lastStepIndex) {
        return false;
    }

    const roundSets = block.sets.filter((s) => s.group_type === 'warm_up' && s.set_index === set.set_index);

    return roundSets.every((s) => s.completed || s.id === set.id);
}

export function warmUpRestSeconds(block: PlayerBlock): number {
    return block.sets.find((s) => s.group_type === 'warm_up')?.rest_seconds ?? 0;
}

export function workingRestSeconds(block: PlayerBlock): number {
    return block.sets.find((s) => s.group_type === 'working')?.rest_seconds ?? 0;
}

export function workingRoundsInBlock(block: PlayerBlock): number {
    const indexes = new Set(block.sets.filter((s) => s.group_type === 'working').map((s) => s.set_index));
    return indexes.size;
}

/** Planned sets in the same group for this exercise (x of n). */
export function plannedSetCount(block: PlayerBlock, set: PlayerSet): number {
    return block.sets.filter((s) => s.group_type === set.group_type && s.workout_block_exercise_id === set.workout_block_exercise_id).length;
}

export function nextDropSegmentWeight(lastKg: number): number {
    return Math.max(0, Math.round((lastKg - 2.5) * 2) / 2);
}

export function defaultPromoteSegments(workingKg: number): Array<{ weight_kg: number }> {
    return [{ weight_kg: workingKg }, { weight_kg: nextDropSegmentWeight(workingKg) }];
}

export function visitLeavesWorkout(visit: { url: string | URL }, workoutId: string): boolean {
    const url = typeof visit.url === 'string' ? new URL(visit.url, window.location.origin) : visit.url;
    return !url.pathname.startsWith(`/workouts/${workoutId}`);
}
