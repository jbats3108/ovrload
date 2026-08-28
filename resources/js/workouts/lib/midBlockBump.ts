import type { PlayerBlockExercise } from '@/workouts/types';

export const BUMP_KG = 2.5;

export type ProgressionStyle = 'straight_sets' | 'progressive_overload';
export type ProgressiveMidBlock = 'ask' | 'auto';

export type MidBlockBumpOffer = {
    exerciseId: number;
    suggestedWeightKg: number;
};

export function qualifiesForMidBlockBump(
    exercise: PlayerBlockExercise,
    loggedWeightKg: number,
    loggedReps: number,
    options: {
        mode: string;
        progressionStyle: ProgressionStyle;
        blockIsAdHoc: boolean;
        isDropset: boolean;
        groupType: string;
    },
): boolean {
    if (options.progressionStyle !== 'progressive_overload') {
        return false;
    }
    if (options.mode === 'deload') {
        return false;
    }
    if (options.blockIsAdHoc) {
        return false;
    }
    if (options.isDropset || options.groupType !== 'working') {
        return false;
    }
    if (loggedReps < exercise.prescribed_reps) {
        return false;
    }
    if (loggedWeightKg < exercise.working_weight_kg) {
        return false;
    }

    return true;
}

export function bumpedWeightKg(loggedWeightKg: number): number {
    return loggedWeightKg + BUMP_KG;
}

export function workingSetPrefillKg(
    loggedWeightKg: number | null | undefined,
    previousSetWeightKg: number | null | undefined,
    lastWorkingWeightKg: number | undefined,
    targetWeightKg: number | null | undefined,
    bumpPrefillKg: number | undefined,
): number {
    if (loggedWeightKg != null) {
        return loggedWeightKg;
    }
    if (bumpPrefillKg != null) {
        return bumpPrefillKg;
    }
    if (previousSetWeightKg != null) {
        return previousSetWeightKg;
    }
    if (lastWorkingWeightKg != null) {
        return lastWorkingWeightKg;
    }

    return targetWeightKg ?? 0;
}
