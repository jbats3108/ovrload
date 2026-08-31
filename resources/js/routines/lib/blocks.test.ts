import { canSetupAfterBlock, emptyBlock, emptyExercise, normalizeBlock, syncSetupAfterBlockFlags, toggleSuperset } from '@/routines/lib/blocks';
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
