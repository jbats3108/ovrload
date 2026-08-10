import type { HistoricalCreateBlock, HistoricalCreateSet } from '@/workouts/types';
import { describe, expect, it } from 'vitest';
import {
    addWorkingRound,
    buildDraftBlocks,
    datetimeLocalToPayload,
    isFinishedAtInFuture,
    removeWorkingRound,
    roundsForBlock,
    syncWarmUpWeights,
} from './historicalCreate';

const sampleBlocks: HistoricalCreateBlock[] = [
    {
        position: 1,
        is_superset: false,
        exercises: [
            {
                position: 1,
                name: 'Squat',
                equipment: 'barbell',
                working_weight_kg: 100,
                prescribed_reps: 5,
                deload_name: null,
                deload_equipment: null,
                deload_working_weight_kg: null,
            },
        ],
        working_set_count: 1,
        working_sets: [
            {
                exercise_position: 1,
                exercise_name: 'Squat',
                set_index: 0,
                is_dropset: false,
                weight_kg: 100,
                reps: 5,
                segments: [],
            },
        ],
        warm_ups: [],
    },
];

describe('historicalCreate', () => {
    it('scales weights and reps for deload', () => {
        const draft = buildDraftBlocks(sampleBlocks, true, 0.9, 0.8);
        expect(draft[0]?.sets[0]?.weight_kg).toBe(90);
        expect(draft[0]?.sets[0]?.reps).toBe(4);
    });

    it('adds and removes working rounds', () => {
        const [block] = buildDraftBlocks(sampleBlocks, false, 1, 1);
        expect(block).toBeDefined();
        addWorkingRound(block!);
        expect(block!.working_set_count).toBe(2);
        expect(block!.sets).toHaveLength(2);
        expect(removeWorkingRound(block!)).toBe(true);
        expect(block!.working_set_count).toBe(1);
        expect(removeWorkingRound(block!)).toBe(false);
    });

    it('pads datetime-local to seconds', () => {
        expect(datetimeLocalToPayload('2026-08-10T17:30')).toBe('2026-08-10T17:30:00');
    });

    it('groups draft sets into rounds by set_index', () => {
        const [block] = buildDraftBlocks(sampleBlocks, false, 1, 1);
        addWorkingRound(block!);
        const rounds = roundsForBlock(block!);
        expect(rounds).toHaveLength(2);
        expect(rounds[0]?.setIndex).toBe(0);
        expect(rounds[1]?.setIndex).toBe(1);
        expect(rounds[0]?.sets).toHaveLength(1);
    });

    it('derives warm-up weights from first working set', () => {
        const withWarmUps: HistoricalCreateBlock[] = [
            {
                ...sampleBlocks[0]!,
                warm_ups: [
                    {
                        exercise_position: 1,
                        exercise_name: 'Squat',
                        set_index: 0,
                        percent_of_working: 40,
                        reps: 5,
                    },
                ],
            },
        ];
        const [block] = buildDraftBlocks(withWarmUps, false, 1, 1);
        expect(block!.warm_ups[0]?.weight_kg).toBe(40);
        block!.sets[0]!.weight_kg = 120;
        syncWarmUpWeights(block!);
        expect(block!.warm_ups[0]?.weight_kg).toBe(48);
    });

    it('omits warm-ups when building a deload draft', () => {
        const withWarmUps: HistoricalCreateBlock[] = [
            {
                ...sampleBlocks[0]!,
                warm_ups: [
                    {
                        exercise_position: 1,
                        exercise_name: 'Squat',
                        set_index: 0,
                        percent_of_working: 40,
                        reps: 5,
                    },
                ],
            },
        ];
        const [block] = buildDraftBlocks(withWarmUps, true, 0.5, 1);
        expect(block!.warm_ups).toEqual([]);
        expect(block!.sets[0]?.weight_kg).toBe(50);
    });

    it('uses deload alternate name and weight as singles on deload drafts', () => {
        const withAlternate: HistoricalCreateBlock[] = [
            {
                position: 1,
                is_superset: false,
                exercises: [
                    {
                        position: 1,
                        name: 'Squat',
                        equipment: 'barbell',
                        working_weight_kg: 100,
                        prescribed_reps: 5,
                        deload_name: 'Goblet Squat',
                        deload_equipment: 'dumbbell',
                        deload_working_weight_kg: 40,
                    },
                ],
                working_set_count: 1,
                working_sets: [
                    {
                        exercise_position: 1,
                        exercise_name: 'Squat',
                        set_index: 0,
                        is_dropset: true,
                        weight_kg: null,
                        reps: 5,
                        segments: [{ weight_kg: 100 }, { weight_kg: 80 }],
                    } satisfies HistoricalCreateSet,
                ],
                warm_ups: [],
            },
        ];
        const [block] = buildDraftBlocks(withAlternate, true, 0.5, 0.5);
        expect(block!.exercise_names).toEqual(['Goblet Squat']);
        expect(block!.sets[0]?.exercise_name).toBe('Goblet Squat');
        expect(block!.sets[0]?.is_dropset).toBe(false);
        expect(block!.sets[0]?.segments).toEqual([]);
        expect(block!.sets[0]?.weight_kg).toBe(40);
        expect(block!.sets[0]?.reps).toBe(3);
    });

    it('detects future finished-at values', () => {
        expect(isFinishedAtInFuture('2099-01-01T12:00')).toBe(true);
        expect(isFinishedAtInFuture('2020-01-01T12:00')).toBe(false);
        expect(isFinishedAtInFuture('')).toBe(false);
    });
});
