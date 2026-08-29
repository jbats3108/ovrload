import { addWarmUpStep, clearWarmUp, removeWarmUpStep, sanitizeWarmUpStepsForSave, setWarmUpText, warmUpText } from '@/routines/lib/warmUp';
import { block } from '@/test/factories';
import { describe, expect, it } from 'vitest';

describe('warmUpText', () => {
    it('formats compact steps', () => {
        const b = block({
            warm_up: {
                set_count: 2,
                rest_seconds: 60,
                steps: [
                    { percent: 40, reps: 5 },
                    { percent: 60, reps: 3 },
                ],
            },
        });
        expect(warmUpText(b)).toBe('40%×5, 60%×3');
    });
});

describe('setWarmUpText', () => {
    it('parses percent×reps and legacy percent-only', () => {
        const b = block();
        setWarmUpText(b, '40%×5, 60%, 80x1');
        expect(b.warm_up.steps).toEqual([
            { percent: 40, reps: 5, has_setup_after: false },
            { percent: 60, reps: 5, has_setup_after: false },
            { percent: 80, reps: 1, has_setup_after: false },
        ]);
        expect(b.warm_up.set_count).toBe(3);
    });

    it('preserves setup flags by position when text changes', () => {
        const b = block({
            warm_up: {
                set_count: 2,
                rest_seconds: 60,
                steps: [
                    { percent: 40, reps: 5, has_setup_after: true },
                    { percent: 60, reps: 3, has_setup_after: false },
                ],
            },
        });
        setWarmUpText(b, '50x5, 70x3, 80x1');
        expect(b.warm_up.steps).toEqual([
            { percent: 50, reps: 5, has_setup_after: true },
            { percent: 70, reps: 3, has_setup_after: false },
            { percent: 80, reps: 1, has_setup_after: false },
        ]);
    });

    it('clears setup-after-warm-up when cleared', () => {
        const b = block({ has_setup_after_warm_up: true });
        clearWarmUp(b);
        expect(b.has_setup_after_warm_up).toBe(false);
    });
});

describe('sanitizeWarmUpStepsForSave', () => {
    it('drops zero empty and non-finite steps', () => {
        expect(
            sanitizeWarmUpStepsForSave([
                { percent: 40, reps: 5, has_setup_after: true },
                { percent: 0, reps: 5 },
                { percent: 60, reps: 0 },
                { percent: Number.NaN, reps: 5 },
                { percent: 80, reps: 1 },
            ]),
        ).toEqual([
            { percent: 40, reps: 5, has_setup_after: true },
            { percent: 80, reps: 1, has_setup_after: false },
        ]);
    });
});

describe('warm-up step helpers', () => {
    it('adds and removes steps', () => {
        const b = block({ warm_up: { set_count: 0, rest_seconds: 60, steps: [] } });
        addWarmUpStep(b);
        expect(b.warm_up.steps).toHaveLength(1);
        removeWarmUpStep(b, 0);
        expect(b.warm_up.steps).toHaveLength(0);
    });
});
