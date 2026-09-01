import { optionalRepsPlaceholder } from '@/routines/lib/optionalReps';
import type { BlockExercise } from '@/routines/types';
import type { ExerciseProfileOption } from '@/settings/types';

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

export function derivedAchievementFloor(prescribedReps: number): number {
    return Math.max(1, prescribedReps - 2);
}

export function editorFloorPlaceholder(
    exercise: BlockExercise,
    profile: ExerciseProfileOption | null,
    assignmentCurrent: boolean,
    userDefault: number | null | undefined,
): string {
    if (profile !== null && assignmentCurrent) {
        return String(resolvedProfileFloor(profile));
    }

    if (exercise.floor_is_derived === true) {
        return String(derivedAchievementFloor(exercise.prescribed_reps));
    }

    return optionalRepsPlaceholder(userDefault);
}

export {
    applyProfileToBlock,
    applyProfileToExercise,
    applyProfileToSupersetExercise,
    applySharedProfileToBlock,
    markExerciseProfileCustom,
    markSharedProfileCustom,
} from '@/routines/lib/exerciseProfileApply';
export {
    exerciseAssignmentFingerprint,
    profileMatchesExerciseAssignment,
    profileMatchesSharedAssignment,
} from '@/routines/lib/exerciseProfileAssignment';
