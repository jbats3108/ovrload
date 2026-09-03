import type { Block, BlockExercise, WarmUpStep } from '@/routines/types';

export type ExerciseCustomiseSnapshot = {
    exercise_profile_id: number | null;
    exercise_profile_fingerprint: string | null;
    prescribed_reps: number;
    achievement_floor: number | null;
    floor_is_derived: boolean | null;
};

export type SharedCustomiseSnapshot = {
    shared_profile_id: number | null;
    shared_profile_fingerprint: string | null;
    rest_seconds: number;
    warm_up: {
        set_count: number;
        rest_seconds: number;
        steps: WarmUpStep[];
    };
};

export function captureExerciseCustomiseSnapshot(exercise: BlockExercise): ExerciseCustomiseSnapshot {
    return {
        exercise_profile_id: exercise.exercise_profile_id ?? null,
        exercise_profile_fingerprint: exercise.exercise_profile_fingerprint ?? null,
        prescribed_reps: exercise.prescribed_reps,
        achievement_floor: exercise.achievement_floor,
        floor_is_derived: exercise.floor_is_derived ?? null,
    };
}

export function restoreExerciseCustomiseSnapshot(exercise: BlockExercise, snapshot: ExerciseCustomiseSnapshot): void {
    exercise.exercise_profile_id = snapshot.exercise_profile_id;
    exercise.exercise_profile_fingerprint = snapshot.exercise_profile_fingerprint;
    exercise.prescribed_reps = snapshot.prescribed_reps;
    exercise.achievement_floor = snapshot.achievement_floor;
    exercise.floor_is_derived = snapshot.floor_is_derived;
}

export function captureSharedCustomiseSnapshot(block: Block): SharedCustomiseSnapshot {
    return {
        shared_profile_id: block.shared_profile_id ?? null,
        shared_profile_fingerprint: block.shared_profile_fingerprint ?? null,
        rest_seconds: block.working.rest_seconds,
        warm_up: {
            set_count: block.warm_up.set_count,
            rest_seconds: block.warm_up.rest_seconds,
            steps: block.warm_up.steps.map((step) => ({ ...step })),
        },
    };
}

export function restoreSharedCustomiseSnapshot(block: Block, snapshot: SharedCustomiseSnapshot): void {
    block.shared_profile_id = snapshot.shared_profile_id;
    block.shared_profile_fingerprint = snapshot.shared_profile_fingerprint;
    block.working.rest_seconds = snapshot.rest_seconds;
    block.warm_up.set_count = snapshot.warm_up.set_count;
    block.warm_up.rest_seconds = snapshot.warm_up.rest_seconds;
    block.warm_up.steps = snapshot.warm_up.steps.map((step) => ({ ...step }));
}
