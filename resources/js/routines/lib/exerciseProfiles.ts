import type { Block, BlockExercise, ExerciseProfileOption, WarmUpStep } from '@/routines/types';

export function coerceProfileId(id: number | string | null | undefined): number | null {
    if (id === null || id === undefined || id === '') {
        return null;
    }

    const parsed = typeof id === 'number' ? id : Number(id);

    return Number.isFinite(parsed) ? parsed : null;
}

export function achievementFloorForSave(exercise: BlockExercise): number | null {
    if (exercise.floor_is_derived === true) {
        return null;
    }

    return typeof exercise.achievement_floor === 'number' && Number.isFinite(exercise.achievement_floor) && exercise.achievement_floor >= 1
        ? exercise.achievement_floor
        : null;
}

export function normalizeExerciseForEditor(exercise: BlockExercise): BlockExercise {
    const normalized = {
        ...exercise,
        exercise_profile_id: exercise.exercise_profile_id ?? null,
        exercise_profile_fingerprint: exercise.exercise_profile_fingerprint ?? null,
        floor_is_derived: exercise.floor_is_derived ?? null,
    };

    if (normalized.floor_is_derived === true) {
        normalized.achievement_floor = null;
    }

    return normalized;
}

export function resolvedProfileFloor(profile: ExerciseProfileOption): number {
    return profile.floor;
}

export function applySharedProfileToBlock(block: Block, profile: ExerciseProfileOption, includeWarmUp = true): void {
    const existingSetupFlags = block.warm_up.steps.map((step) => Boolean(step.has_setup_after));

    block.working.rest_seconds = profile.working_rest_seconds;
    if (includeWarmUp) {
        block.shared_profile_id = profile.id;
        block.shared_profile_fingerprint = profile.shared_fingerprint;
        block.warm_up.steps = profile.warm_up_steps.map((step, index): WarmUpStep => {
            const mode = step.mode ?? 'percent';

            return {
                mode,
                percent: mode === 'percent' ? step.percent : undefined,
                weight_kg: mode === 'fixed' ? step.weight_kg : undefined,
                reps: step.reps,
                has_setup_after: existingSetupFlags[index] ?? false,
            };
        });
        block.warm_up.set_count = block.warm_up.steps.length;
    } else {
        markSharedProfileCustom(block);
    }
}

export function applyProfileToBlock(block: Block, profile: ExerciseProfileOption, includeWarmUp = true): void {
    applySharedProfileToBlock(block, profile, includeWarmUp);

    block.exercises.forEach((exercise) => {
        applyProfileToExercise(exercise, profile, block.is_superset || !includeWarmUp);
    });
}

export function applyProfileToExercise(exercise: BlockExercise, profile: ExerciseProfileOption, isSuperset: boolean): void {
    exercise.prescribed_reps = profile.target_reps;
    exercise.achievement_floor = profile.floor_override;
    exercise.floor_is_derived = profile.floor_override === null;
    exercise.exercise_profile_id = profile.id;
    exercise.exercise_profile_fingerprint = isSuperset ? profile.exercise_fingerprint : profile.recipe_fingerprint;
}

export function applyProfileToSupersetExercise(block: Block, exerciseIndex: number, profile: ExerciseProfileOption): void {
    const exercise = block.exercises[exerciseIndex];

    if (!exercise || !block.is_superset) {
        return;
    }

    applyProfileToExercise(exercise, profile, true);
}

export function markExerciseProfileCustom(exercise: BlockExercise): void {
    exercise.exercise_profile_id = null;
    exercise.exercise_profile_fingerprint = null;
}

export function markSharedProfileCustom(block: Block): void {
    block.shared_profile_id = null;
    block.shared_profile_fingerprint = null;
}

export function profileMatchesExerciseAssignment(
    exercise: BlockExercise,
    profile: ExerciseProfileOption,
    isSuperset: boolean,
    sharedProfileMatchesExercise = false,
): boolean {
    return (
        exercise.exercise_profile_id === profile.id &&
        exercise.exercise_profile_fingerprint ===
            (isSuperset || !sharedProfileMatchesExercise ? profile.exercise_fingerprint : profile.recipe_fingerprint)
    );
}

export function profileMatchesSharedAssignment(block: Block, profile: ExerciseProfileOption): boolean {
    return block.shared_profile_id === profile.id && block.shared_profile_fingerprint === profile.shared_fingerprint;
}
