import { defaultBarG, gramsToKg, usesBarbellPlates } from '@/lib/plateCalculator';
import type { PlateProfile } from '@/settings/types';
import type { HistoricalCreateBlock, HistoricalCreateSet, HistoricalCreateWarmUp } from '@/workouts/types';

export type DraftSet = {
    exercise_position: number;
    exercise_name: string;
    set_index: number;
    is_dropset: boolean;
    weight_kg: number | null;
    reps: number;
    segments: { weight_kg: number }[];
};

export type DraftWarmUpSet = {
    exercise_position: number;
    exercise_name: string;
    set_index: number;
    weight_mode: 'percent' | 'bar' | 'fixed';
    percent_of_working: number | null;
    preset_weight_kg: number | null;
    reps: number;
    weight_kg: number;
    equipment: string | null;
};

export type DraftBlock = {
    position: number;
    is_superset: boolean;
    exercise_names: string[];
    working_set_count: number;
    sets: DraftSet[];
    warm_ups: DraftWarmUpSet[];
};

function scaleWeight(kg: number, factor: number): number {
    return Math.round(kg * factor * 1000) / 1000;
}

function scaleReps(reps: number, factor: number): number {
    return Math.max(1, Math.round(reps * factor));
}

function defaultBarKg(plateProfile: PlateProfile | null | undefined): number | null {
    if (!plateProfile) {
        return null;
    }

    const barG = defaultBarG(plateProfile.bars);
    return barG === null ? null : gramsToKg(barG);
}

export function firstWorkingWeightKg(block: DraftBlock, exercisePosition: number): number {
    const first = block.sets.find((set) => set.exercise_position === exercisePosition && set.set_index === 0);
    if (!first) {
        return 0;
    }

    if (first.is_dropset && first.segments.length > 0) {
        return first.segments[0]?.weight_kg ?? 0;
    }

    return first.weight_kg ?? 0;
}

function resolveWarmUpWeightKg(
    recipe: Pick<HistoricalCreateWarmUp, 'weight_mode' | 'percent_of_working' | 'weight_kg'>,
    baseWeightKg: number,
    equipment: string | null,
    defaultBarKgValue: number | null,
): number {
    if (recipe.weight_mode === 'fixed') {
        return recipe.weight_kg ?? 0;
    }

    if (recipe.weight_mode === 'bar') {
        if (usesBarbellPlates(equipment) && defaultBarKgValue != null) {
            return defaultBarKgValue;
        }

        return 0;
    }

    return scaleWeight(baseWeightKg * ((recipe.percent_of_working ?? 0) / 100), 1);
}

export function syncWarmUpWeights(block: DraftBlock, plateProfile?: PlateProfile | null): void {
    const barKg = defaultBarKg(plateProfile);
    for (const warmUp of block.warm_ups) {
        if (warmUp.weight_mode === 'fixed') {
            warmUp.weight_kg = warmUp.preset_weight_kg ?? warmUp.weight_kg;
            continue;
        }

        const base = firstWorkingWeightKg(block, warmUp.exercise_position);
        warmUp.weight_kg = resolveWarmUpWeightKg(warmUp, base, warmUp.equipment, barKg);
    }
}

function warmUpFromRecipe(
    recipe: HistoricalCreateWarmUp,
    baseWeightKg: number,
    equipment: string | null,
    defaultBarKgValue: number | null,
): DraftWarmUpSet {
    return {
        exercise_position: recipe.exercise_position,
        exercise_name: recipe.exercise_name,
        set_index: recipe.set_index,
        weight_mode: recipe.weight_mode,
        percent_of_working: recipe.percent_of_working,
        preset_weight_kg: recipe.weight_kg,
        reps: recipe.reps,
        equipment,
        weight_kg: resolveWarmUpWeightKg(recipe, baseWeightKg, equipment, defaultBarKgValue),
    };
}

export function buildDraftBlocks(
    blocks: HistoricalCreateBlock[],
    deload: boolean,
    weightFactor: number,
    repsFactor: number,
    plateProfile?: PlateProfile | null,
): DraftBlock[] {
    const w = deload ? weightFactor : 1;
    const r = deload ? repsFactor : 1;
    const barKg = defaultBarKg(plateProfile);

    return blocks.map((block) => {
        const sets = block.working_sets.map((set) => scaleSet(set, block, deload, w, r));
        const draft: DraftBlock = {
            position: block.position,
            is_superset: block.is_superset,
            exercise_names: block.exercises.map((exercise) => (deload && exercise.deload_name ? exercise.deload_name : exercise.name)),
            working_set_count: block.working_set_count,
            sets,
            warm_ups: [],
        };

        if (!deload) {
            draft.warm_ups = (block.warm_ups ?? []).map((recipe) => {
                const exercise = block.exercises.find((row) => row.position === recipe.exercise_position);

                return warmUpFromRecipe(recipe, firstWorkingWeightKg(draft, recipe.exercise_position), exercise?.equipment ?? null, barKg);
            });
        }

        return draft;
    });
}

function scaleSet(set: HistoricalCreateSet, block: HistoricalCreateBlock, deload: boolean, weightFactor: number, repsFactor: number): DraftSet {
    const exercise = block.exercises.find((row) => row.position === set.exercise_position);

    if (deload && exercise != null && exercise.deload_name != null && exercise.deload_working_weight_kg != null) {
        return {
            exercise_position: set.exercise_position,
            exercise_name: exercise.deload_name,
            set_index: set.set_index,
            is_dropset: false,
            weight_kg: exercise.deload_working_weight_kg,
            reps: scaleReps(set.reps, repsFactor),
            segments: [],
        };
    }

    return {
        exercise_position: set.exercise_position,
        exercise_name: set.exercise_name,
        set_index: set.set_index,
        is_dropset: set.is_dropset,
        weight_kg: set.weight_kg === null ? null : scaleWeight(set.weight_kg, weightFactor),
        reps: scaleReps(set.reps, repsFactor),
        segments: set.segments.map((segment) => ({
            weight_kg: scaleWeight(segment.weight_kg, weightFactor),
        })),
    };
}

export function addWorkingRound(block: DraftBlock): void {
    const maxIndex = Math.max(-1, ...block.sets.map((set) => set.set_index));
    const nextIndex = maxIndex + 1;
    const template = block.sets.filter((set) => set.set_index === maxIndex);

    for (const set of template) {
        block.sets.push({
            ...set,
            set_index: nextIndex,
            segments: set.segments.map((segment) => ({ ...segment })),
        });
    }

    block.working_set_count += 1;
}

export function removeWorkingRound(block: DraftBlock): boolean {
    if (block.working_set_count <= 1) {
        return false;
    }

    const maxIndex = Math.max(...block.sets.map((set) => set.set_index));
    block.sets = block.sets.filter((set) => set.set_index !== maxIndex);
    block.working_set_count -= 1;

    return true;
}

export function blockTitle(block: DraftBlock): string {
    if (block.exercise_names.length === 0) {
        return 'Exercise';
    }

    if (block.is_superset || block.exercise_names.length > 1) {
        return block.exercise_names.join(' + ');
    }

    return block.exercise_names[0] ?? 'Exercise';
}

/** Group draft sets into rounds (same set_index) for clearer Create UI. */
export function roundsForBlock(block: DraftBlock): Array<{ setIndex: number; sets: DraftSet[] }> {
    const byIndex = new Map<number, DraftSet[]>();

    for (const set of block.sets) {
        const existing = byIndex.get(set.set_index);
        if (existing) {
            existing.push(set);
            continue;
        }
        byIndex.set(set.set_index, [set]);
    }

    return [...byIndex.entries()]
        .sort(([a], [b]) => a - b)
        .map(([setIndex, sets]) => ({
            setIndex,
            sets: [...sets].sort((a, b) => a.exercise_position - b.exercise_position),
        }));
}

export function warmUpRoundsForBlock(block: DraftBlock): Array<{ setIndex: number; sets: DraftWarmUpSet[] }> {
    const byIndex = new Map<number, DraftWarmUpSet[]>();

    for (const set of block.warm_ups) {
        const existing = byIndex.get(set.set_index);
        if (existing) {
            existing.push(set);
            continue;
        }
        byIndex.set(set.set_index, [set]);
    }

    return [...byIndex.entries()]
        .sort(([a], [b]) => a - b)
        .map(([setIndex, sets]) => ({
            setIndex,
            sets: [...sets].sort((a, b) => a.exercise_position - b.exercise_position),
        }));
}

export function toDatetimeLocalValue(date = new Date()): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** App-timezone wall clock for Spatie/Carbon (no UTC shift). */
export function datetimeLocalToPayload(value: string): string {
    if (value.length === 16) {
        return `${value}:00`;
    }

    return value;
}

export function isFinishedAtInFuture(value: string): boolean {
    if (!value) {
        return false;
    }

    const date = new Date(datetimeLocalToPayload(value));

    return Number.isNaN(date.getTime()) ? false : date.getTime() > Date.now();
}
