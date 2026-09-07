import type { PlayerBlock, PlayerSet } from '@/workouts/types';

/** Trim kg for display (supports 2dp loads like 28.75). */
export function formatKg(kg: number | null | undefined): string {
    if (kg == null) {
        return '—';
    }

    return String(parseFloat(kg.toFixed(2)));
}

/** Heading for a block: exercise name(s). Fallback ordinal uses UI noun “Exercise”. */
export function historyBlockTitle(block: PlayerBlock): string {
    const names = [...block.exercises].sort((a, b) => a.position - b.position).map((exercise) => exercise.name);

    if (names.length === 0) {
        return `Exercise ${block.position}`;
    }

    if (block.type === 'circuit') {
        return names.join(' · ');
    }

    if (block.is_superset && names.length >= 2) {
        return `${names[0]} / ${names[1]}`;
    }

    return names[0] ?? `Exercise ${block.position}`;
}

/** Warm-up loads for display: one group (null name) or per-exercise for supersets. */
export function historyWarmUpGroups(sets: PlayerSet[]): Array<{ exerciseName: string | null; loads: string[] }> {
    if (sets.length === 0) {
        return [];
    }

    const multi = new Set(sets.map((set) => set.exercise_name)).size > 1;
    if (!multi) {
        return [
            {
                exerciseName: null,
                loads: sets.map((set) => `${set.logged_reps ?? '—'}×${formatKg(set.logged_weight_kg ?? set.target_weight_kg)}`),
            },
        ];
    }

    const groups = new Map<number, { exerciseName: string; loads: string[] }>();
    for (const set of sets) {
        const load = `${set.logged_reps ?? '—'}×${formatKg(set.logged_weight_kg ?? set.target_weight_kg)}`;
        const existing = groups.get(set.workout_block_exercise_id);
        if (existing) {
            existing.loads.push(load);
            continue;
        }
        groups.set(set.workout_block_exercise_id, { exerciseName: set.exercise_name, loads: [load] });
    }

    return [...groups.values()].map((group) => ({ exerciseName: group.exerciseName, loads: group.loads }));
}

export type HistoryBlockRow =
    | { type: 'warm_up'; key: string; sets: PlayerSet[] }
    | { type: 'working_group'; key: string; exerciseName: string; sets: PlayerSet[] };

/** One warm-up cluster + one working group per exercise (set order preserved). */
export function historyRowsForBlock(sets: PlayerSet[]): HistoryBlockRow[] {
    const rows: HistoryBlockRow[] = [];
    const warmUps = sets.filter((set) => set.group_type === 'warm_up');
    const working = sets.filter((set) => set.group_type === 'working');

    if (warmUps.length > 0) {
        rows.push({ type: 'warm_up', key: `warm_up-${warmUps[0].id}`, sets: warmUps });
    }

    const groups = new Map<number, PlayerSet[]>();
    for (const set of working) {
        const existing = groups.get(set.workout_block_exercise_id);
        if (existing) {
            existing.push(set);
            continue;
        }
        groups.set(set.workout_block_exercise_id, [set]);
    }

    for (const [exerciseId, groupSets] of groups) {
        rows.push({
            type: 'working_group',
            key: `working-${exerciseId}`,
            exerciseName: groupSets[0].exercise_name,
            sets: [...groupSets].sort((a, b) => a.set_index - b.set_index),
        });
    }

    return rows;
}

/** Format a single circuit set result for summary display. */
export function formatSetSummary(set: PlayerSet): string {
    if (set.is_skipped) {
        return 'Skipped';
    }

    const weightKg = set.logged_weight_kg ?? set.target_weight_kg;
    const weightText = weightKg != null && weightKg > 0 ? ` @ ${formatKg(weightKg)}kg` : '';

    if (set.prescription_mode === 'duration') {
        if (
            set.logged_duration_seconds != null &&
            set.target_duration_seconds != null &&
            set.logged_duration_seconds !== set.target_duration_seconds
        ) {
            return `${set.target_duration_seconds}s target · ${set.logged_duration_seconds}s${weightText}`;
        }

        const duration = set.logged_duration_seconds ?? set.target_duration_seconds ?? 0;
        return `${duration}s${weightText}`;
    }

    const reps = set.logged_reps ?? set.target_reps ?? 0;
    return `${reps} reps${weightText}`;
}

export function areSetsIdentical(sets: PlayerSet[]): boolean {
    if (sets.length <= 1) {
        return true;
    }

    const first = sets[0];
    const firstSkipped = Boolean(first.is_skipped);
    const firstReps = first.logged_reps ?? first.target_reps;
    const firstDuration = first.logged_duration_seconds ?? first.target_duration_seconds;
    const firstWeight = first.logged_weight_kg ?? first.target_weight_kg;

    for (let i = 1; i < sets.length; i++) {
        const current = sets[i];
        if (Boolean(current.is_skipped) !== firstSkipped) {
            return false;
        }
        if ((current.logged_reps ?? current.target_reps) !== firstReps) {
            return false;
        }
        if ((current.logged_duration_seconds ?? current.target_duration_seconds) !== firstDuration) {
            return false;
        }
        if ((current.logged_weight_kg ?? current.target_weight_kg) !== firstWeight) {
            return false;
        }
    }

    return true;
}

export type CircuitExerciseSummary = {
    exerciseId: number;
    exerciseName: string;
    prescriptionMode: 'reps' | 'duration';
    isIdentical: boolean;
    summaryText: string;
    sets: PlayerSet[];
};

export function circuitExerciseSummaries(sets: PlayerSet[]): CircuitExerciseSummary[] {
    const working = sets.filter((set) => set.group_type === 'working');
    const groups = new Map<number, PlayerSet[]>();

    for (const set of working) {
        const existing = groups.get(set.workout_block_exercise_id);
        if (existing) {
            existing.push(set);
            continue;
        }
        groups.set(set.workout_block_exercise_id, [set]);
    }

    const summaries: CircuitExerciseSummary[] = [];

    for (const [exerciseId, groupSets] of groups) {
        const sorted = [...groupSets].sort((a, b) => a.set_index - b.set_index);
        const identical = areSetsIdentical(sorted);
        const summaryText = identical ? `${formatSetSummary(sorted[0])} · ×${sorted.length} rounds` : `${sorted.length} rounds (varied)`;

        summaries.push({
            exerciseId,
            exerciseName: sorted[0].exercise_name,
            prescriptionMode: sorted[0].prescription_mode ?? 'reps',
            isIdentical: identical,
            summaryText,
            sets: sorted,
        });
    }

    return summaries;
}
