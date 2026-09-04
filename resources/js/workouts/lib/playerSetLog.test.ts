import { gramsToKg } from '@/lib/plateCalculator';
import { buildCompleteSetPayload } from '@/workouts/lib/playerSetLog';
import type { PlayerSet } from '@/workouts/types';
import { describe, expect, it } from 'vitest';

function workingSet(overrides: Partial<PlayerSet> = {}): PlayerSet {
    return {
        id: 1,
        workout_block_exercise_id: 1,
        exercise_name: 'Bench Press',
        group_type: 'working',
        set_index: 0,
        rest_seconds: 120,
        target_reps: 6,
        target_weight_kg: 60,
        completed: false,
        is_dropset: false,
        has_setup_after: false,
        equipment: 'barbell',
        logged_reps: null,
        logged_weight_kg: null,
        plate_stack: null,
        segments: [],
        ...overrides,
    };
}

describe('buildCompleteSetPayload', () => {
    it('builds a working-set payload with optional plate stack', () => {
        const payload = buildCompleteSetPayload(workingSet(), 6, 60, [], {
            exact: true,
            total_g: 60000,
            per_side: [{ denomination_g: 20000, count: 1, colour: null }],
            bar_g: 20000,
            delta_g: 0,
        });

        expect(payload).toEqual({
            reps: 6,
            weight_kg: 60,
            plate_stack: {
                bar_g: 20000,
                per_side: [{ denomination_g: 20000, count: 1 }],
            },
        });
    });

    it('omits plate stack when load does not match entered weight', () => {
        const payload = buildCompleteSetPayload(workingSet(), 6, 62.5, [], {
            exact: false,
            total_g: 60000,
            per_side: [{ denomination_g: 20000, count: 1, colour: null }],
            bar_g: 20000,
            delta_g: -2500,
        });

        expect(payload).toEqual({
            reps: 6,
            weight_kg: 62.5,
            plate_stack: null,
        });
    });

    it('persists a matching stack even when the load was marked inexact for a prior target', () => {
        const payload = buildCompleteSetPayload(workingSet(), 6, 60, [], {
            exact: false,
            total_g: 60000,
            per_side: [{ denomination_g: 20000, count: 1, colour: null }],
            bar_g: 20000,
            delta_g: -2500,
        });

        expect(payload).toEqual({
            reps: 6,
            weight_kg: 60,
            plate_stack: {
                bar_g: 20000,
                per_side: [{ denomination_g: 20000, count: 1 }],
            },
        });
    });

    it('builds a dropset payload from draft segments', () => {
        const payload = buildCompleteSetPayload(workingSet({ is_dropset: true }), 8, 0, [{ weight_kg: 60 }, { weight_kg: 45 }], null);

        expect(payload).toEqual({
            reps: 8,
            segments: [{ weight_kg: 60 }, { weight_kg: 45 }],
        });
    });

    it('accepts matching plate load within gram rounding', () => {
        const payload = buildCompleteSetPayload(workingSet(), 6, gramsToKg(60250), [], {
            exact: true,
            total_g: 60250,
            per_side: [{ denomination_g: 20000, count: 1, colour: null }],
            bar_g: 20250,
            delta_g: 0,
        });

        expect(payload).toMatchObject({
            reps: 6,
            weight_kg: gramsToKg(60250),
            plate_stack: {
                bar_g: 20250,
            },
        });
    });
});
