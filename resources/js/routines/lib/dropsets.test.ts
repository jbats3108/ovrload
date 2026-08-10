import {
    addDropsetSegment,
    applyRunTheRack,
    defaultDropsetSegments,
    dropsetSummary,
    isDropsetSlot,
    removeDropsetSegment,
    setSlotKind,
    trimDropsetsToSetCount,
} from '@/routines/lib/dropsets';
import { block } from '@/test/factories';
import { describe, expect, it } from 'vitest';

describe('dropsets', () => {
    it('creates dropset slot with at least two segments', () => {
        const b = block();
        setSlotKind(b, 0, 'dropset');
        expect(isDropsetSlot(b, 0)).toBe(true);
    });

    it('ignores dropsets on supersets', () => {
        const b = block({ is_superset: true });
        setSlotKind(b, 0, 'dropset');
        expect(isDropsetSlot(b, 0)).toBe(false);
    });

    it('fills run-the-rack segments', () => {
        const b = block();
        applyRunTheRack(b, 0, { start: 20, end: 15, step: 2.5 });
        expect(b.working.dropsets[0]?.segments.map((s) => s.weight_kg)).toEqual([20, 17.5, 15]);
    });

    it('trims dropsets beyond set count', () => {
        const b = block({
            working: {
                set_count: 2,
                rest_seconds: 120,
                dropsets: [
                    { set_index: 0, segments: [{ weight_kg: 60 }, { weight_kg: 50 }] },
                    { set_index: 2, segments: [{ weight_kg: 40 }, { weight_kg: 30 }] },
                ],
            },
        });
        trimDropsetsToSetCount(b);
        expect(b.working.dropsets).toHaveLength(1);
    });

    it('summarizes dropset recipes', () => {
        const b = block({
            working: {
                set_count: 3,
                rest_seconds: 120,
                dropsets: [{ set_index: 0, segments: [{ weight_kg: 60 }, { weight_kg: 50 }] }],
            },
        });
        expect(dropsetSummary(b)).toBe('S1:60→50');
    });

    it('reverts slot to single', () => {
        const b = block();
        setSlotKind(b, 0, 'dropset');
        setSlotKind(b, 0, 'single');
        expect(isDropsetSlot(b, 0)).toBe(false);
    });

    it('defaults segments from working weight', () => {
        const b = block({
            exercises: [
                {
                    exercise_id: 1,
                    working_weight_kg: 100,
                    prescribed_reps: 5,
                    achievement_floor: null,
                    progression_target: null,
                    deload_exercise_id: null,
                    deload_working_weight_kg: null,
                },
            ],
        });
        expect(defaultDropsetSegments(b)).toEqual([{ weight_kg: 100 }, { weight_kg: 90 }]);
    });

    it('adds and removes dropset segments', () => {
        const b = block();
        setSlotKind(b, 0, 'dropset');
        addDropsetSegment(b, 0);
        expect(b.working.dropsets[0]?.segments).toHaveLength(3);
        removeDropsetSegment(b, 0, 2);
        expect(b.working.dropsets[0]?.segments).toHaveLength(2);
        removeDropsetSegment(b, 0, 0);
        expect(b.working.dropsets[0]?.segments).toHaveLength(2);
    });

    it('ignores invalid run-the-rack input', () => {
        const b = block();
        applyRunTheRack(b, 0, { start: 10, end: 20, step: 2.5 });
        expect(b.working.dropsets).toHaveLength(0);
    });
});
