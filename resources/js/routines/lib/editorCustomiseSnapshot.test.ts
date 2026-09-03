import {
    captureExerciseCustomiseSnapshot,
    captureSharedCustomiseSnapshot,
    restoreExerciseCustomiseSnapshot,
    restoreSharedCustomiseSnapshot,
} from '@/routines/lib/editorCustomiseSnapshot';
import type { Block, BlockExercise } from '@/routines/types';
import { describe, expect, it } from 'vitest';

function exercise(overrides: Partial<BlockExercise> = {}): BlockExercise {
    return {
        exercise_id: 1,
        working_weight_kg: 100,
        prescribed_reps: 6,
        achievement_floor: null,
        floor_is_derived: true,
        progression_target: null,
        deload_exercise_id: null,
        deload_working_weight_kg: null,
        exercise_profile_id: 1,
        exercise_profile_fingerprint: 'exercise-strength',
        ...overrides,
    };
}

function block(overrides: Partial<Block> = {}): Block {
    return {
        is_superset: false,
        has_setup_after: false,
        has_setup_after_warm_up: false,
        shared_profile_id: 1,
        shared_profile_fingerprint: 'shared-strength',
        exercises: [exercise()],
        working: { set_count: 3, rest_seconds: 180, dropsets: [] },
        warm_up: {
            set_count: 1,
            rest_seconds: 60,
            steps: [{ mode: 'percent', percent: 50, reps: 5, has_setup_after: false }],
        },
        ...overrides,
    };
}

describe('editorCustomiseSnapshot', () => {
    it('round-trips exercise profile fields without touching deload alt', () => {
        const ex = exercise({
            prescribed_reps: 8,
            achievement_floor: 5,
            floor_is_derived: false,
            deload_exercise_id: 2,
            deload_working_weight_kg: 80,
        });
        const snapshot = captureExerciseCustomiseSnapshot(ex);

        ex.exercise_profile_id = null;
        ex.exercise_profile_fingerprint = null;
        ex.prescribed_reps = 12;
        ex.achievement_floor = 9;
        ex.deload_exercise_id = 3;

        restoreExerciseCustomiseSnapshot(ex, snapshot);

        expect(ex.exercise_profile_id).toBe(1);
        expect(ex.exercise_profile_fingerprint).toBe('exercise-strength');
        expect(ex.prescribed_reps).toBe(8);
        expect(ex.achievement_floor).toBe(5);
        expect(ex.floor_is_derived).toBe(false);
        expect(ex.deload_exercise_id).toBe(3);
        expect(ex.deload_working_weight_kg).toBe(80);
    });

    it('round-trips shared rest and warm-up', () => {
        const target = block();
        const snapshot = captureSharedCustomiseSnapshot(target);

        target.shared_profile_id = null;
        target.shared_profile_fingerprint = null;
        target.working.rest_seconds = 45;
        target.warm_up.steps = [];
        target.warm_up.set_count = 0;

        restoreSharedCustomiseSnapshot(target, snapshot);

        expect(target.shared_profile_id).toBe(1);
        expect(target.shared_profile_fingerprint).toBe('shared-strength');
        expect(target.working.rest_seconds).toBe(180);
        expect(target.warm_up.steps).toEqual([{ mode: 'percent', percent: 50, reps: 5, has_setup_after: false }]);
        expect(target.warm_up.set_count).toBe(1);
    });
});
