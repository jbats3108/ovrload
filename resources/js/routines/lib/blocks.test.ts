import {
    addCircuitExercise,
    assignMissingExerciseIds,
    canSetupAfterBlock,
    emptyBlock,
    emptyExercise,
    isCircuitBlock,
    moveCircuitExercise,
    normalizeBlock,
    removeCircuitExercise,
    setExercisePrescriptionMode,
    swapSupersetExercises,
    syncSetupAfterBlockFlags,
    togglePrescriptionMode,
    toggleSuperset,
} from '@/routines/lib/blocks';
import { block } from '@/test/factories';
import { describe, expect, it } from 'vitest';

describe('emptyExercise', () => {
    it('uses catalog id when provided', () => {
        expect(emptyExercise(42).exercise_id).toBe(42);
        expect(emptyExercise(42).deload_exercise_id).toBeNull();
        expect(emptyExercise(42).deload_working_weight_kg).toBeNull();
        expect(emptyExercise(42).prescribed_reps).toBe(6);
    });

    it('uses prescribed reps when provided', () => {
        expect(emptyExercise(42, 10).prescribed_reps).toBe(10);
    });
});

describe('emptyBlock', () => {
    it('seeds warm-up defaults', () => {
        const b = emptyBlock({ warmUpDefaults: [{ percent: 40, reps: 5 }] });
        expect(b.warm_up.steps).toEqual([{ mode: 'percent', percent: 40, reps: 5, has_setup_after: false }]);
    });

    it('preserves bar warm-up defaults', () => {
        const b = emptyBlock({ warmUpDefaults: [{ mode: 'bar', reps: 10 }] });
        expect(b.warm_up.steps).toEqual([{ mode: 'bar', percent: undefined, reps: 10, has_setup_after: false }]);
    });

    it('creates superset with two exercises', () => {
        const b = emptyBlock({ superset: true, seedWarmUp: false, firstCatalogId: 1 });
        expect(b.is_superset).toBe(true);
        expect(b.exercises).toHaveLength(2);
    });

    it('seeds prescribed reps from options', () => {
        const b = emptyBlock({ seedWarmUp: false, prescribedReps: 10 });
        expect(b.exercises[0].prescribed_reps).toBe(10);
    });
});

describe('normalizeBlock', () => {
    it('preserves bar warm-up mode from the server', () => {
        const raw = block({
            warm_up: {
                set_count: 2,
                rest_seconds: 60,
                steps: [
                    { mode: 'bar', reps: 10, has_setup_after: false },
                    { mode: 'percent', percent: 50, reps: 5, has_setup_after: false },
                ],
            },
        });

        expect(normalizeBlock(raw).warm_up.steps).toEqual([
            { mode: 'bar', percent: undefined, reps: 10, has_setup_after: false },
            { mode: 'percent', percent: 50, reps: 5, has_setup_after: false },
        ]);
    });

    it('drops invalid dropsets and clears superset dropsets', () => {
        const raw = block({
            is_superset: true,
            working: {
                set_count: 3,
                rest_seconds: 90,
                dropsets: [{ set_index: 0, segments: [{ weight_kg: 60 }, { weight_kg: 50 }] }],
            },
        });
        expect(normalizeBlock(raw).working.dropsets).toEqual([]);
    });

    it('clears setup-after-warm-up when no warm-up steps', () => {
        const raw = block({
            has_setup_after_warm_up: true,
            warm_up: { set_count: 0, rest_seconds: 60, steps: [] },
        });
        expect(normalizeBlock(raw).has_setup_after_warm_up).toBe(false);
    });
});

describe('syncSetupAfterBlockFlags', () => {
    it('clears setup-after on the final block', () => {
        const blocks = [block({ has_setup_after: true }), block({ has_setup_after: true })];
        syncSetupAfterBlockFlags(blocks);
        expect(blocks[0].has_setup_after).toBe(true);
        expect(blocks[1].has_setup_after).toBe(false);
    });
});

describe('canSetupAfterBlock', () => {
    it('allows every block except the last', () => {
        expect(canSetupAfterBlock(0, 3)).toBe(true);
        expect(canSetupAfterBlock(1, 3)).toBe(true);
        expect(canSetupAfterBlock(2, 3)).toBe(false);
    });
});

describe('toggleSuperset', () => {
    it('adds second exercise and clears dropsets when enabling superset', () => {
        const b = block({
            working: {
                set_count: 3,
                rest_seconds: 120,
                dropsets: [{ set_index: 0, segments: [{ weight_kg: 60 }, { weight_kg: 50 }] }],
            },
        });
        toggleSuperset(b, 99);
        expect(b.is_superset).toBe(true);
        expect(b.exercises).toHaveLength(2);
        expect(b.working.dropsets).toEqual([]);
    });
});

describe('swapSupersetExercises', () => {
    it('swaps A and B including weights and profiles', () => {
        const a = emptyExercise(1);
        a.working_weight_kg = 100;
        a.exercise_profile_id = 10;
        const bEx = emptyExercise(2);
        bEx.working_weight_kg = 40;
        bEx.exercise_profile_id = 20;
        const b = block({
            is_superset: true,
            exercises: [a, bEx],
        });

        expect(swapSupersetExercises(b)).toBe(true);
        expect(b.exercises[0].exercise_id).toBe(2);
        expect(b.exercises[0].working_weight_kg).toBe(40);
        expect(b.exercises[0].exercise_profile_id).toBe(20);
        expect(b.exercises[1].exercise_id).toBe(1);
        expect(b.exercises[1].working_weight_kg).toBe(100);
        expect(b.exercises[1].exercise_profile_id).toBe(10);
    });

    it('is a no-op for non-supersets', () => {
        const b = block({ is_superset: false });
        const only = b.exercises[0];
        expect(swapSupersetExercises(b)).toBe(false);
        expect(b.exercises[0]).toBe(only);
    });
});

describe('circuit blocks', () => {
    it('creates an empty circuit block with 3 exercises and dual rest defaults', () => {
        const b = emptyBlock({ type: 'circuit', firstCatalogId: 10, prescribedReps: 8 });
        expect(isCircuitBlock(b)).toBe(true);
        expect(b.type).toBe('circuit');
        expect(b.stage_rest_seconds).toBe(15);
        expect(b.working.rest_seconds).toBe(60);
        expect(b.warm_up.steps).toHaveLength(0);
        expect(b.exercises).toHaveLength(3);
        expect(b.exercises[0].exercise_id).toBe(10);
        expect(b.exercises[0].prescribed_reps).toBe(8);
        expect(b.exercises[0].prescription_mode).toBe('reps');
    });

    it('adds and removes exercises respecting the 3 exercise minimum', () => {
        const b = emptyBlock({ type: 'circuit', firstCatalogId: 1 });
        expect(b.exercises).toHaveLength(3);

        // Cannot remove when length <= 3
        expect(removeCircuitExercise(b, 0)).toBe(false);
        expect(b.exercises).toHaveLength(3);

        // Add 4th exercise
        addCircuitExercise(b, 2, 12);
        expect(b.exercises).toHaveLength(4);
        expect(b.exercises[3].exercise_id).toBe(2);
        expect(b.exercises[3].prescribed_reps).toBe(12);

        // Can now remove
        expect(removeCircuitExercise(b, 1)).toBe(true);
        expect(b.exercises).toHaveLength(3);
        expect(removeCircuitExercise(b, 0)).toBe(false);
    });

    it('reorders circuit exercises safely', () => {
        const b = emptyBlock({ type: 'circuit', firstCatalogId: 1 });
        b.exercises[0].exercise_id = 101;
        b.exercises[1].exercise_id = 102;
        b.exercises[2].exercise_id = 103;

        expect(moveCircuitExercise(b, 0, 2)).toBe(true);
        expect(b.exercises.map((e) => e.exercise_id)).toEqual([102, 103, 101]);

        expect(moveCircuitExercise(b, -1, 1)).toBe(false);
        expect(moveCircuitExercise(b, 0, 99)).toBe(false);
        expect(moveCircuitExercise(b, 1, 1)).toBe(false);
    });

    it('toggles and sets prescription mode between reps and duration', () => {
        const ex = emptyExercise(1, 10);
        ex.achievement_floor = 8;
        ex.exercise_profile_id = 5;

        setExercisePrescriptionMode(ex, 'duration');
        expect(ex.prescription_mode).toBe('duration');
        expect(ex.prescribed_duration_seconds).toBe(30);
        expect(ex.prescribed_reps).toBeNull();
        expect(ex.achievement_floor).toBeNull();
        expect(ex.exercise_profile_id).toBeNull();

        togglePrescriptionMode(ex);
        expect(ex.prescription_mode).toBe('reps');
        expect(ex.prescribed_reps).toBe(10);
        expect(ex.prescribed_duration_seconds).toBeNull();
    });

    it('normalizes circuit blocks by stripping warm-ups, dropsets, and deload alternates', () => {
        const raw = block({
            type: 'circuit',
            stage_rest_seconds: 20,
            working: {
                set_count: 4,
                rest_seconds: 90,
                dropsets: [{ set_index: 0, segments: [{ weight_kg: 50 }, { weight_kg: 40 }] }],
            },
            warm_up: {
                set_count: 2,
                rest_seconds: 60,
                steps: [{ mode: 'percent', percent: 50, reps: 5, has_setup_after: false }],
            },
            exercises: [
                { ...emptyExercise(1, 10), deload_exercise_id: 2, deload_working_weight_kg: 40 },
                { ...emptyExercise(3, null, 'duration', 45) },
                { ...emptyExercise(4, 12) },
            ],
        });

        const normalized = normalizeBlock(raw);
        expect(normalized.type).toBe('circuit');
        expect(normalized.stage_rest_seconds).toBe(20);
        expect(normalized.working.rest_seconds).toBe(90);
        expect(normalized.working.dropsets).toEqual([]);
        expect(normalized.warm_up.steps).toEqual([]);
        expect(normalized.exercises[0].deload_working_weight_kg).toBeNull();
        expect(normalized.exercises[1].prescription_mode).toBe('duration');
        expect(normalized.exercises[1].prescribed_duration_seconds).toBe(45);
        expect(normalized.exercises[1].prescribed_reps).toBeNull();
    });

    it('assigns a catalog id only to exercises that are still empty', () => {
        const filled = emptyBlock({ type: 'circuit', firstCatalogId: 10 });
        filled.exercises[1].exercise_id = null;
        assignMissingExerciseIds([filled], 4);

        expect(filled.exercises.map((exercise) => exercise.exercise_id)).toEqual([10, 4, 10]);
    });

    it('is a no-op when the catalog has not loaded', () => {
        const empty = emptyBlock({ type: 'circuit' });
        assignMissingExerciseIds([empty], null);
        expect(empty.exercises.every((exercise) => exercise.exercise_id === null)).toBe(true);
    });
});
