import { normalizeExerciseForEditor } from '@/routines/lib/exerciseProfiles';
import type { Block, BlockExercise, WarmUpStep } from '@/routines/types';
import { normalizeEditorWarmUpStep } from '@/shared/warmUpStep';

export function emptyExercise(firstCatalogId: number | null = null, prescribedReps = 6): BlockExercise {
    return {
        exercise_id: firstCatalogId,
        exercise_profile_id: null,
        exercise_profile_fingerprint: null,
        working_weight_kg: 60,
        prescribed_reps: prescribedReps,
        achievement_floor: null,
        progression_target: null,
        deload_exercise_id: null,
        deload_working_weight_kg: null,
    };
}

export function clearDeloadAlternate(exercise: BlockExercise): void {
    exercise.deload_exercise_id = null;
    exercise.deload_working_weight_kg = null;
}

export function setDeloadAlternateExercise(exercise: BlockExercise, exerciseId: number | null): void {
    exercise.deload_exercise_id = exerciseId;
    if (exerciseId === null) {
        exercise.deload_working_weight_kg = null;
        return;
    }
    if (exercise.deload_working_weight_kg === null) {
        exercise.deload_working_weight_kg = exercise.working_weight_kg;
    }
}

export function emptyBlock(
    options: {
        superset?: boolean;
        seedWarmUp?: boolean;
        warmUpDefaults?: WarmUpStep[];
        firstCatalogId?: number | null;
        prescribedReps?: number;
    } = {},
): Block {
    const { superset = false, seedWarmUp = true, warmUpDefaults = [], firstCatalogId = null, prescribedReps = 6 } = options;
    const steps = seedWarmUp ? warmUpDefaults.map((s) => normalizeEditorWarmUpStep({ ...s, has_setup_after: false })) : [];
    return {
        is_superset: superset,
        has_setup_after: false,
        has_setup_after_warm_up: false,
        shared_profile_id: null,
        shared_profile_fingerprint: null,
        exercises: superset
            ? [emptyExercise(firstCatalogId, prescribedReps), emptyExercise(firstCatalogId, prescribedReps)]
            : [emptyExercise(firstCatalogId, prescribedReps)],
        working: { set_count: 3, rest_seconds: 120, dropsets: [] },
        warm_up: { set_count: steps.length, rest_seconds: 60, steps },
    };
}

export function syncSetupAfterBlockFlags(blocks: Block[]): void {
    if (blocks.length === 0) {
        return;
    }

    const lastIndex = blocks.length - 1;
    blocks.forEach((block, index) => {
        if (index === lastIndex) {
            block.has_setup_after = false;
        }
    });
}

export function canSetupAfterBlock(blockIndex: number, blockCount: number): boolean {
    return blockCount > 0 && blockIndex < blockCount - 1;
}

export function normalizeBlock(raw: Block): Block {
    const steps = (raw.warm_up?.steps ?? []).map((s) => normalizeEditorWarmUpStep(s));
    const dropsets = (raw.working?.dropsets ?? [])
        .map((d) => ({
            set_index: Number(d.set_index),
            segments: (d.segments ?? []).map((s) => ({ weight_kg: Number(s.weight_kg) })),
        }))
        .filter((d) => d.segments.length >= 2);
    return {
        ...raw,
        shared_profile_id: raw.shared_profile_id ?? null,
        shared_profile_fingerprint: raw.shared_profile_fingerprint ?? null,
        has_setup_after_warm_up: Boolean(raw.has_setup_after_warm_up) && steps.length > 0,
        exercises: (raw.exercises ?? []).map((exercise) =>
            normalizeExerciseForEditor({
                ...emptyExercise(),
                ...exercise,
                deload_working_weight_kg: exercise.deload_exercise_id != null ? (exercise.deload_working_weight_kg ?? null) : null,
            }),
        ),
        working: {
            set_count: raw.working?.set_count ?? 3,
            rest_seconds: raw.working?.rest_seconds ?? 120,
            dropsets: raw.is_superset ? [] : dropsets,
        },
        warm_up: {
            set_count: raw.warm_up?.set_count ?? steps.length,
            rest_seconds: raw.warm_up?.rest_seconds ?? 60,
            steps,
        },
    };
}

export function toggleSuperset(block: Block, firstCatalogId: number | null = null, prescribedReps = 6): void {
    block.is_superset = !block.is_superset;
    if (block.is_superset && block.exercises.length < 2) {
        block.exercises.push(emptyExercise(firstCatalogId, prescribedReps));
    }
    if (!block.is_superset && block.exercises.length > 1) {
        block.exercises = [block.exercises[0]];
    }
    if (block.is_superset) {
        block.working.dropsets = [];
    }
}
