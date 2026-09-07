import {
    areSetsIdentical,
    circuitExerciseSummaries,
    formatKg,
    formatSetSummary,
    historyBlockTitle,
    historyRowsForBlock,
    historyWarmUpGroups,
} from '@/workouts/lib/historyDisplay';
import type { PlayerBlock, PlayerSet } from '@/workouts/types';
import { describe, expect, it } from 'vitest';

const set = (overrides: Partial<PlayerSet> & Pick<PlayerSet, 'id' | 'group_type'>): PlayerSet => ({
    workout_block_exercise_id: 1,
    exercise_name: 'Bench',
    equipment: 'barbell',
    set_index: 0,
    target_weight_kg: null,
    target_reps: null,
    logged_weight_kg: null,
    plate_stack: null,
    logged_reps: null,
    completed: true,
    rest_seconds: 60,
    has_setup_after: false,
    is_dropset: false,
    segments: [],
    ...overrides,
});

describe('formatKg', () => {
    it('formats whole and 2dp weights', () => {
        expect(formatKg(80)).toBe('80');
        expect(formatKg(28.75)).toBe('28.75');
        expect(formatKg(28.7500001)).toBe('28.75');
        expect(formatKg(null)).toBe('—');
    });
});

describe('historyBlockTitle', () => {
    it('uses the single exercise name', () => {
        const block = {
            id: 1,
            position: 1,
            is_superset: false,
            is_ad_hoc: false,
            is_parked: false,
            has_setup_after: false,
            has_setup_after_warm_up: false,
            exercises: [
                { id: 1, name: 'Squat', working_weight_kg: 100, prescribed_reps: 5, achievement_floor: null, progression_target: null, position: 0 },
            ],
            sets: [],
        } satisfies PlayerBlock;

        expect(historyBlockTitle(block)).toBe('Squat');
    });

    it('joins superset exercises with a slash', () => {
        const block = {
            id: 2,
            position: 2,
            is_superset: true,
            is_ad_hoc: false,
            is_parked: false,
            has_setup_after: false,
            has_setup_after_warm_up: false,
            exercises: [
                { id: 10, name: 'Press', working_weight_kg: 50, prescribed_reps: 8, achievement_floor: null, progression_target: null, position: 0 },
                { id: 11, name: 'Row', working_weight_kg: 60, prescribed_reps: 10, achievement_floor: null, progression_target: null, position: 1 },
            ],
            sets: [],
        } satisfies PlayerBlock;

        expect(historyBlockTitle(block)).toBe('Press / Row');
    });

    it('joins circuit exercises with a middle dot', () => {
        const block = {
            id: 3,
            position: 1,
            type: 'circuit',
            is_superset: false,
            is_ad_hoc: false,
            is_parked: false,
            has_setup_after: false,
            has_setup_after_warm_up: false,
            exercises: [
                {
                    id: 10,
                    name: 'Push-up',
                    working_weight_kg: 0,
                    prescribed_reps: 15,
                    achievement_floor: null,
                    progression_target: null,
                    position: 0,
                },
                {
                    id: 11,
                    name: 'Plank',
                    working_weight_kg: 0,
                    prescribed_reps: null,
                    achievement_floor: null,
                    progression_target: null,
                    position: 1,
                },
                { id: 12, name: 'Squat', working_weight_kg: 20, prescribed_reps: 12, achievement_floor: null, progression_target: null, position: 2 },
            ],
            sets: [],
        } satisfies PlayerBlock;

        expect(historyBlockTitle(block)).toBe('Push-up · Plank · Squat');
    });
});

describe('historyWarmUpGroups', () => {
    it('keeps a single-exercise warm-up as one load line', () => {
        expect(
            historyWarmUpGroups([
                set({ id: 1, group_type: 'warm_up', logged_reps: 5, logged_weight_kg: 40 }),
                set({ id: 2, group_type: 'warm_up', set_index: 1, logged_reps: 3, logged_weight_kg: 50 }),
            ]),
        ).toEqual([{ exerciseName: null, loads: ['5×40', '3×50'] }]);
    });

    it('groups multi-exercise warm-ups by exercise', () => {
        expect(
            historyWarmUpGroups([
                set({
                    id: 1,
                    group_type: 'warm_up',
                    workout_block_exercise_id: 10,
                    exercise_name: 'Press',
                    set_index: 0,
                    logged_reps: 5,
                    logged_weight_kg: 40,
                }),
                set({
                    id: 2,
                    group_type: 'warm_up',
                    workout_block_exercise_id: 11,
                    exercise_name: 'Row',
                    set_index: 0,
                    logged_reps: 5,
                    logged_weight_kg: 30,
                }),
                set({
                    id: 3,
                    group_type: 'warm_up',
                    workout_block_exercise_id: 10,
                    exercise_name: 'Press',
                    set_index: 1,
                    logged_reps: 3,
                    logged_weight_kg: 50,
                }),
                set({
                    id: 4,
                    group_type: 'warm_up',
                    workout_block_exercise_id: 11,
                    exercise_name: 'Row',
                    set_index: 1,
                    logged_reps: 3,
                    logged_weight_kg: 40,
                }),
            ]),
        ).toEqual([
            { exerciseName: 'Press', loads: ['5×40', '3×50'] },
            { exerciseName: 'Row', loads: ['5×30', '3×40'] },
        ]);
    });
});

describe('historyRowsForBlock', () => {
    it('collapses warm-ups and groups working sets by exercise', () => {
        const rows = historyRowsForBlock([
            set({ id: 1, group_type: 'warm_up', logged_reps: 5, logged_weight_kg: 40 }),
            set({ id: 2, group_type: 'warm_up', logged_reps: 3, logged_weight_kg: 50 }),
            set({ id: 3, group_type: 'working', set_index: 0, logged_reps: 6, logged_weight_kg: 80 }),
            set({ id: 4, group_type: 'working', set_index: 1, logged_reps: 6, logged_weight_kg: 80 }),
        ]);

        expect(rows).toHaveLength(2);
        expect(rows[0]).toMatchObject({ type: 'warm_up', sets: [{ id: 1 }, { id: 2 }] });
        expect(rows[1]).toMatchObject({
            type: 'working_group',
            exerciseName: 'Bench',
            sets: [{ id: 3 }, { id: 4 }],
        });
    });

    it('keeps separate working groups for a superset pair', () => {
        const rows = historyRowsForBlock([
            set({
                id: 1,
                group_type: 'working',
                workout_block_exercise_id: 10,
                exercise_name: 'Press',
                set_index: 0,
            }),
            set({
                id: 2,
                group_type: 'working',
                workout_block_exercise_id: 11,
                exercise_name: 'Row',
                set_index: 0,
            }),
            set({
                id: 3,
                group_type: 'working',
                workout_block_exercise_id: 10,
                exercise_name: 'Press',
                set_index: 1,
            }),
            set({
                id: 4,
                group_type: 'working',
                workout_block_exercise_id: 11,
                exercise_name: 'Row',
                set_index: 1,
            }),
        ]);

        expect(rows).toEqual([
            expect.objectContaining({
                type: 'working_group',
                exerciseName: 'Press',
                sets: [expect.objectContaining({ id: 1 }), expect.objectContaining({ id: 3 })],
            }),
            expect.objectContaining({
                type: 'working_group',
                exerciseName: 'Row',
                sets: [expect.objectContaining({ id: 2 }), expect.objectContaining({ id: 4 })],
            }),
        ]);
    });
});

describe('formatSetSummary', () => {
    it('formats skipped set', () => {
        expect(formatSetSummary(set({ id: 1, group_type: 'working', is_skipped: true }))).toBe('Skipped');
    });

    it('formats rep-based set with and without weight', () => {
        expect(formatSetSummary(set({ id: 1, group_type: 'working', logged_reps: 10, logged_weight_kg: 25 }))).toBe('10 reps @ 25kg');
        expect(formatSetSummary(set({ id: 1, group_type: 'working', logged_reps: 15, logged_weight_kg: 0 }))).toBe('15 reps');
    });

    it('formats duration set with and without target divergence', () => {
        expect(
            formatSetSummary(
                set({
                    id: 1,
                    group_type: 'working',
                    prescription_mode: 'duration',
                    target_duration_seconds: 30,
                    logged_duration_seconds: 30,
                    logged_weight_kg: 0,
                }),
            ),
        ).toBe('30s');

        expect(
            formatSetSummary(
                set({
                    id: 1,
                    group_type: 'working',
                    prescription_mode: 'duration',
                    target_duration_seconds: 30,
                    logged_duration_seconds: 25,
                    logged_weight_kg: 10,
                }),
            ),
        ).toBe('30s target · 25s @ 10kg');
    });
});

describe('areSetsIdentical and circuitExerciseSummaries', () => {
    it('detects identical rounds and formats xN summary', () => {
        const sets = [
            set({
                id: 1,
                group_type: 'working',
                workout_block_exercise_id: 10,
                exercise_name: 'Push-up',
                set_index: 0,
                logged_reps: 12,
                logged_weight_kg: 0,
            }),
            set({
                id: 2,
                group_type: 'working',
                workout_block_exercise_id: 10,
                exercise_name: 'Push-up',
                set_index: 1,
                logged_reps: 12,
                logged_weight_kg: 0,
            }),
            set({
                id: 3,
                group_type: 'working',
                workout_block_exercise_id: 10,
                exercise_name: 'Push-up',
                set_index: 2,
                logged_reps: 12,
                logged_weight_kg: 0,
            }),
        ];

        expect(areSetsIdentical(sets)).toBe(true);
        const summaries = circuitExerciseSummaries(sets);
        expect(summaries).toHaveLength(1);
        expect(summaries[0].isIdentical).toBe(true);
        expect(summaries[0].summaryText).toBe('12 reps · ×3 rounds');
    });

    it('detects varied rounds and flags non-identical', () => {
        const sets = [
            set({
                id: 1,
                group_type: 'working',
                workout_block_exercise_id: 10,
                exercise_name: 'Push-up',
                set_index: 0,
                logged_reps: 12,
                logged_weight_kg: 0,
            }),
            set({
                id: 2,
                group_type: 'working',
                workout_block_exercise_id: 10,
                exercise_name: 'Push-up',
                set_index: 1,
                logged_reps: 10,
                logged_weight_kg: 0,
            }),
        ];

        expect(areSetsIdentical(sets)).toBe(false);
        const summaries = circuitExerciseSummaries(sets);
        expect(summaries[0].isIdentical).toBe(false);
        expect(summaries[0].summaryText).toBe('2 rounds (varied)');
    });
});
