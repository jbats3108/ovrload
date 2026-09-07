import { normalizeExerciseForEditor } from '@/routines/lib/exerciseProfiles';
import type { Block, BlockExercise, WarmUpStep } from '@/routines/types';
import { normalizeEditorWarmUpStep } from '@/shared/warmUpStep';

export function emptyExercise(
    firstCatalogId: number | null = null,
    prescribedReps: number | null = 6,
    prescriptionMode: 'reps' | 'duration' = 'reps',
    prescribedDurationSeconds: number | null = null,
): BlockExercise {
    return {
        exercise_id: firstCatalogId,
        exercise_profile_id: null,
        exercise_profile_fingerprint: null,
        working_weight_kg: 60,
        prescribed_reps: prescriptionMode === 'duration' ? null : (prescribedReps ?? 6),
        prescription_mode: prescriptionMode,
        prescribed_duration_seconds: prescriptionMode === 'duration' ? (prescribedDurationSeconds ?? 30) : null,
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
        type?: 'single' | 'superset' | 'circuit';
        superset?: boolean;
        seedWarmUp?: boolean;
        warmUpDefaults?: WarmUpStep[];
        firstCatalogId?: number | null;
        prescribedReps?: number;
    } = {},
): Block {
    const { type, superset = false, seedWarmUp = true, warmUpDefaults = [], firstCatalogId = null, prescribedReps = 6 } = options;
    const resolvedType = type ?? (superset ? 'superset' : 'single');
    const isCircuit = resolvedType === 'circuit';
    const isSuperset = resolvedType === 'superset';
    const steps = !isCircuit && seedWarmUp ? warmUpDefaults.map((s) => normalizeEditorWarmUpStep({ ...s, has_setup_after: false })) : [];

    let exercises: BlockExercise[];
    if (isCircuit) {
        exercises = [
            emptyExercise(firstCatalogId, prescribedReps),
            emptyExercise(firstCatalogId, prescribedReps),
            emptyExercise(firstCatalogId, prescribedReps),
        ];
    } else if (isSuperset) {
        exercises = [emptyExercise(firstCatalogId, prescribedReps), emptyExercise(firstCatalogId, prescribedReps)];
    } else {
        exercises = [emptyExercise(firstCatalogId, prescribedReps)];
    }

    return {
        type: resolvedType,
        stage_rest_seconds: isCircuit ? 15 : null,
        is_superset: isSuperset,
        has_setup_after: false,
        has_setup_after_warm_up: false,
        shared_profile_id: null,
        shared_profile_fingerprint: null,
        exercises,
        working: { set_count: 3, rest_seconds: isCircuit ? 60 : 120, dropsets: [] },
        warm_up: { set_count: steps.length, rest_seconds: isCircuit ? 0 : 60, steps },
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
    const blockType = raw.type ?? (raw.is_superset ? 'superset' : 'single');
    const isCircuit = blockType === 'circuit';
    const steps = isCircuit ? [] : (raw.warm_up?.steps ?? []).map((s) => normalizeEditorWarmUpStep(s));
    const dropsets = (raw.working?.dropsets ?? [])
        .map((d) => ({
            set_index: Number(d.set_index),
            segments: (d.segments ?? []).map((s) => ({ weight_kg: Number(s.weight_kg) })),
        }))
        .filter((d) => d.segments.length >= 2);
    return {
        ...raw,
        type: blockType,
        stage_rest_seconds: isCircuit ? (raw.stage_rest_seconds ?? 15) : null,
        shared_profile_id: isCircuit ? null : (raw.shared_profile_id ?? null),
        shared_profile_fingerprint: isCircuit ? null : (raw.shared_profile_fingerprint ?? null),
        has_setup_after_warm_up: !isCircuit && Boolean(raw.has_setup_after_warm_up) && steps.length > 0,
        exercises: (raw.exercises ?? []).map((exercise) =>
            normalizeExerciseForEditor({
                ...emptyExercise(),
                ...exercise,
                prescription_mode: exercise.prescription_mode ?? 'reps',
                prescribed_reps: exercise.prescription_mode === 'duration' ? null : (exercise.prescribed_reps ?? 6),
                prescribed_duration_seconds: exercise.prescription_mode === 'duration' ? (exercise.prescribed_duration_seconds ?? 30) : null,
                deload_working_weight_kg: !isCircuit && exercise.deload_exercise_id != null ? (exercise.deload_working_weight_kg ?? null) : null,
            }),
        ),
        working: {
            set_count: raw.working?.set_count ?? 3,
            rest_seconds: raw.working?.rest_seconds ?? (isCircuit ? 60 : 120),
            dropsets: isCircuit || raw.is_superset ? [] : dropsets,
        },
        warm_up: {
            set_count: isCircuit ? 0 : (raw.warm_up?.set_count ?? steps.length),
            rest_seconds: isCircuit ? 0 : (raw.warm_up?.rest_seconds ?? 60),
            steps,
        },
    };
}

export function isCircuitBlock(block: Block): boolean {
    return block.type === 'circuit';
}

export function addCircuitExercise(block: Block, firstCatalogId: number | null = null, prescribedReps = 6): void {
    block.exercises.push(emptyExercise(firstCatalogId, prescribedReps));
}

export function removeCircuitExercise(block: Block, index: number): boolean {
    if (block.exercises.length <= 3) {
        return false;
    }
    block.exercises.splice(index, 1);
    return true;
}

export function moveCircuitExercise(block: Block, fromIndex: number, toIndex: number): boolean {
    if (fromIndex < 0 || fromIndex >= block.exercises.length || toIndex < 0 || toIndex >= block.exercises.length || fromIndex === toIndex) {
        return false;
    }
    const item = block.exercises.splice(fromIndex, 1)[0];
    if (item === undefined) {
        return false;
    }
    block.exercises.splice(toIndex, 0, item);
    return true;
}

export function setExercisePrescriptionMode(exercise: BlockExercise, mode: 'reps' | 'duration'): void {
    if (exercise.prescription_mode === mode) {
        return;
    }
    if (mode === 'duration') {
        exercise.prescription_mode = 'duration';
        exercise.prescribed_duration_seconds = exercise.prescribed_duration_seconds ?? 30;
        exercise.prescribed_reps = null;
        exercise.achievement_floor = null;
        exercise.progression_target = null;
        exercise.exercise_profile_id = null;
        exercise.exercise_profile_fingerprint = null;
    } else {
        exercise.prescription_mode = 'reps';
        exercise.prescribed_reps = exercise.prescribed_reps ?? 10;
        exercise.prescribed_duration_seconds = null;
    }
}

export function togglePrescriptionMode(exercise: BlockExercise): void {
    setExercisePrescriptionMode(exercise, exercise.prescription_mode === 'duration' ? 'reps' : 'duration');
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

/** Swap A↔B in a superset. No-op when not a two-exercise superset. Returns whether a swap happened. */
export function swapSupersetExercises(block: Block): boolean {
    if (!block.is_superset || block.exercises.length < 2) {
        return false;
    }

    const [first, second] = block.exercises;
    if (first === undefined || second === undefined) {
        return false;
    }

    block.exercises[0] = second;
    block.exercises[1] = first;

    return true;
}

export function assignMissingExerciseIds(blocks: Block[], catalogId: number | null): void {
    if (catalogId === null) {
        return;
    }

    blocks.forEach((block) => {
        block.exercises.forEach((exercise) => {
            if (exercise.exercise_id === null) {
                exercise.exercise_id = catalogId;
            }
        });
    });
}
