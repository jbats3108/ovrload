import { bumpedWeightKg, qualifiesForMidBlockBump, workingSetPrefillKg } from '@/workouts/lib/midBlockBump';
import { describe, expect, it } from 'vitest';

const exercise = {
    id: 1,
    name: 'Bench',
    working_weight_kg: 80,
    prescribed_reps: 6,
    achievement_floor: null,
    progression_target: null,
    position: 1,
};

describe('midBlockBump', () => {
    it('qualifies when target reps are hit at snapshotted working weight', () => {
        expect(
            qualifiesForMidBlockBump(exercise, 80, 6, {
                mode: 'standard',
                progressionStyle: 'progressive_overload',
                blockIsAdHoc: false,
                isDropset: false,
                groupType: 'working',
            }),
        ).toBe(true);
    });

    it('does not qualify under straight sets', () => {
        expect(
            qualifiesForMidBlockBump(exercise, 80, 6, {
                mode: 'standard',
                progressionStyle: 'straight_sets',
                blockIsAdHoc: false,
                isDropset: false,
                groupType: 'working',
            }),
        ).toBe(false);
    });

    it('bumps from logged weight', () => {
        expect(bumpedWeightKg(85)).toBe(87.5);
    });

    it('prefers logged weight over bump prefill', () => {
        expect(workingSetPrefillKg(80, 82.5, 82.5, 80, 87.5)).toBe(80);
    });

    it('uses bump prefill before previous set weight', () => {
        expect(workingSetPrefillKg(null, 80, 80, 80, 82.5)).toBe(82.5);
    });
});
