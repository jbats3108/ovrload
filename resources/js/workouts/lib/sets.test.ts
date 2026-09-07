import { playerBlock, playerSet } from '@/test/factories';
import { flattenPlayerSets } from '@/workouts/lib/focus';
import {
    circuitRoundSets,
    defaultPromoteSegments,
    finishesWarmUpGroup,
    finishesWarmUpStep,
    isCircuit,
    isLastInCircuitRound,
    nextCircuitSet,
    nextDropSegmentWeight,
    nextSupersetSet,
    plannedSetCount,
    previousSetWeightKg,
    shouldRestAfter,
    supersetRoundSets,
    visitLeavesWorkout,
    workingWeightForSet,
} from '@/workouts/lib/sets';
import { describe, expect, it } from 'vitest';

describe('previousSetWeightKg', () => {
    it('returns last completed weight for same exercise', () => {
        const block = playerBlock({
            sets: [playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 80 }), playerSet({ id: 2, set_index: 1, completed: false })],
        });
        const entry = flattenPlayerSets([block])[1];
        expect(previousSetWeightKg(entry)).toBe(80);
    });
});

describe('workingWeightForSet', () => {
    it('prefers exercise working weight', () => {
        const block = playerBlock();
        const entry = flattenPlayerSets([block])[0];
        expect(workingWeightForSet(entry)).toBe(100);
    });
});

describe('shouldRestAfter', () => {
    it('waits for superset round completion', () => {
        const block = playerBlock({
            is_superset: true,
            exercises: [
                { id: 10, name: 'A', working_weight_kg: 50, prescribed_reps: 8, achievement_floor: null, progression_target: null, position: 0 },
                { id: 11, name: 'B', working_weight_kg: 50, prescribed_reps: 8, achievement_floor: null, progression_target: null, position: 1 },
            ],
            sets: [
                playerSet({ id: 1, workout_block_exercise_id: 10, completed: true }),
                playerSet({ id: 2, workout_block_exercise_id: 11, completed: false, set_index: 0 }),
            ],
        });
        const current = block.sets[1];
        expect(shouldRestAfter(block, current)).toBe(true);
    });
});

describe('supersetRoundSets', () => {
    it('returns A then B for a superset round', () => {
        const block = playerBlock({
            is_superset: true,
            exercises: [
                { id: 10, name: 'Press', working_weight_kg: 50, prescribed_reps: 8, achievement_floor: null, progression_target: null, position: 0 },
                { id: 11, name: 'Row', working_weight_kg: 50, prescribed_reps: 8, achievement_floor: null, progression_target: null, position: 1 },
            ],
            sets: [
                playerSet({ id: 2, workout_block_exercise_id: 11, exercise_name: 'Row', set_index: 0 }),
                playerSet({ id: 1, workout_block_exercise_id: 10, exercise_name: 'Press', set_index: 0 }),
            ],
        });
        expect(supersetRoundSets(block, block.sets[0]).map((s) => s.exercise_name)).toEqual(['Press', 'Row']);
    });

    it('returns only the given set outside supersets', () => {
        const block = playerBlock();
        expect(supersetRoundSets(block, block.sets[0])).toEqual([block.sets[0]]);
    });
});

describe('nextSupersetSet', () => {
    it('returns the partner exercise later in the round', () => {
        const block = playerBlock({
            is_superset: true,
            exercises: [
                { id: 10, name: 'Press', working_weight_kg: 50, prescribed_reps: 8, achievement_floor: null, progression_target: null, position: 0 },
                { id: 11, name: 'Row', working_weight_kg: 50, prescribed_reps: 8, achievement_floor: null, progression_target: null, position: 1 },
            ],
            sets: [
                playerSet({ id: 1, workout_block_exercise_id: 10, exercise_name: 'Press', set_index: 0 }),
                playerSet({ id: 2, workout_block_exercise_id: 11, exercise_name: 'Row', set_index: 0 }),
            ],
        });
        expect(nextSupersetSet(block, block.sets[0])?.exercise_name).toBe('Row');
        expect(nextSupersetSet(block, block.sets[1])).toBeNull();
    });

    it('returns null outside supersets', () => {
        const block = playerBlock();
        expect(nextSupersetSet(block, block.sets[0])).toBeNull();
    });
});

describe('circuit helpers', () => {
    const makeCircuitBlock = () =>
        playerBlock({
            type: 'circuit',
            exercises: [
                {
                    id: 10,
                    name: 'Burpees',
                    working_weight_kg: 0,
                    prescribed_reps: null,
                    prescription_mode: 'duration',
                    prescribed_duration_seconds: 45,
                    achievement_floor: null,
                    progression_target: null,
                    position: 0,
                },
                {
                    id: 11,
                    name: 'Kettlebell Swings',
                    working_weight_kg: 24,
                    prescribed_reps: 15,
                    prescription_mode: 'reps',
                    prescribed_duration_seconds: null,
                    achievement_floor: null,
                    progression_target: null,
                    position: 1,
                },
                {
                    id: 12,
                    name: 'Plank',
                    working_weight_kg: 0,
                    prescribed_reps: null,
                    prescription_mode: 'duration',
                    prescribed_duration_seconds: 60,
                    achievement_floor: null,
                    progression_target: null,
                    position: 2,
                },
            ],
            sets: [
                playerSet({ id: 1, workout_block_exercise_id: 10, exercise_name: 'Burpees', set_index: 0, rest_seconds: 15 }),
                playerSet({ id: 2, workout_block_exercise_id: 11, exercise_name: 'Kettlebell Swings', set_index: 0, rest_seconds: 15 }),
                playerSet({ id: 3, workout_block_exercise_id: 12, exercise_name: 'Plank', set_index: 0, rest_seconds: 60 }),
                playerSet({ id: 4, workout_block_exercise_id: 10, exercise_name: 'Burpees', set_index: 1, rest_seconds: 15 }),
                playerSet({ id: 5, workout_block_exercise_id: 11, exercise_name: 'Kettlebell Swings', set_index: 1, rest_seconds: 15 }),
                playerSet({ id: 6, workout_block_exercise_id: 12, exercise_name: 'Plank', set_index: 1, rest_seconds: 60 }),
            ],
        });

    it('identifies circuit block correctly', () => {
        const circuit = makeCircuitBlock();
        expect(isCircuit(circuit)).toBe(true);

        const standard = playerBlock({ type: 'single' });
        expect(isCircuit(standard)).toBe(false);
    });

    it('returns ordered sets for a round', () => {
        const block = makeCircuitBlock();
        const round0 = circuitRoundSets(block, block.sets[0]);
        expect(round0.map((s) => s.id)).toEqual([1, 2, 3]);

        const round1 = circuitRoundSets(block, block.sets[4]);
        expect(round1.map((s) => s.id)).toEqual([4, 5, 6]);
    });

    it('finds the next exercise in the circuit round', () => {
        const block = makeCircuitBlock();
        expect(nextCircuitSet(block, block.sets[0])?.id).toBe(2);
        expect(nextCircuitSet(block, block.sets[1])?.id).toBe(3);
        expect(nextCircuitSet(block, block.sets[2])).toBeNull();
    });

    it('identifies the last exercise in a round', () => {
        const block = makeCircuitBlock();
        expect(isLastInCircuitRound(block, block.sets[0])).toBe(false);
        expect(isLastInCircuitRound(block, block.sets[1])).toBe(false);
        expect(isLastInCircuitRound(block, block.sets[2])).toBe(true);
    });

    it('always rests after every circuit set', () => {
        const block = makeCircuitBlock();
        expect(shouldRestAfter(block, block.sets[0])).toBe(true);
        expect(shouldRestAfter(block, block.sets[1])).toBe(true);
        expect(shouldRestAfter(block, block.sets[2])).toBe(true);
    });
});

describe('finishesWarmUpStep', () => {
    it('is true when a mid warm-up round completes with setup after', () => {
        const block = playerBlock({
            sets: [
                playerSet({ id: 1, group_type: 'warm_up', set_index: 0, completed: true, has_setup_after: true }),
                playerSet({ id: 2, group_type: 'warm_up', set_index: 1, completed: false, has_setup_after: false }),
            ],
        });
        expect(finishesWarmUpStep(block, block.sets[0])).toBe(true);
    });

    it('is false on the last warm-up step even when flagged', () => {
        const block = playerBlock({
            sets: [
                playerSet({ id: 1, group_type: 'warm_up', set_index: 0, completed: true, has_setup_after: false }),
                playerSet({ id: 2, group_type: 'warm_up', set_index: 1, completed: false, has_setup_after: true }),
            ],
        });
        expect(finishesWarmUpStep(block, block.sets[1])).toBe(false);
    });
});

describe('finishesWarmUpGroup', () => {
    it('is true on last warm-up set', () => {
        const block = playerBlock({
            sets: [
                playerSet({ id: 1, group_type: 'warm_up', completed: true }),
                playerSet({ id: 2, group_type: 'warm_up', completed: false, set_index: 1 }),
            ],
        });
        expect(finishesWarmUpGroup(block, block.sets[1])).toBe(true);
    });
});

describe('plannedSetCount', () => {
    it('counts sets in the same group for the exercise', () => {
        const block = playerBlock({
            sets: [
                playerSet({ id: 1, set_index: 0 }),
                playerSet({ id: 2, set_index: 1 }),
                playerSet({ id: 3, set_index: 2 }),
                playerSet({ id: 4, group_type: 'warm_up', set_index: 0 }),
            ],
        });
        expect(plannedSetCount(block, block.sets[1])).toBe(3);
    });
});

describe('dropset helpers', () => {
    it('steps down by 2.5kg', () => {
        expect(nextDropSegmentWeight(60)).toBe(57.5);
        expect(defaultPromoteSegments(60)).toEqual([{ weight_kg: 60 }, { weight_kg: 57.5 }]);
    });
});

describe('visitLeavesWorkout', () => {
    it('detects navigation away from workout', () => {
        expect(visitLeavesWorkout({ url: '/dashboard' }, '01TESTULID')).toBe(true);
        expect(visitLeavesWorkout({ url: '/workouts/01TESTULID' }, '01TESTULID')).toBe(false);
    });
});
