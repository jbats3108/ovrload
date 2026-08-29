import { emptyBlock } from '@/routines/lib/blocks';
import type { Block, ExerciseOption, RoutinePayload } from '@/routines/types';
import type { PlateProfile } from '@/settings/types';
import type { PlayerBlock, PlayerSet, WorkoutPayload } from '@/workouts/types';

export function exerciseOption(overrides: Partial<ExerciseOption> = {}): ExerciseOption {
    return {
        id: 1,
        name: 'Bench Press',
        primary_muscle_group: 'Chest',
        is_custom: false,
        ...overrides,
    };
}

export function routinePayload(overrides: Partial<RoutinePayload> = {}): RoutinePayload {
    return {
        id: 1,
        slug: 'test-routine',
        name: 'Test Routine',
        default_exercise_profile_id: null,
        deload_weight_factor: 0.8,
        deload_reps_factor: 0.8,
        deload_every_n: 3,
        updated_at: '2026-01-01T00:00:00+00:00',
        blocks: [emptyBlock()],
        ...overrides,
    };
}

export function playerSet(overrides: Partial<PlayerSet> = {}): PlayerSet {
    return {
        id: 1,
        workout_block_exercise_id: 10,
        exercise_name: 'Squat',
        equipment: 'barbell',
        set_index: 0,
        group_type: 'working',
        target_weight_kg: 100,
        target_reps: 5,
        logged_weight_kg: null,
        plate_stack: null,
        logged_reps: null,
        completed: false,
        rest_seconds: 120,
        has_setup_after: false,
        is_dropset: false,
        segments: [],
        ...overrides,
    };
}

export function playerBlock(overrides: Partial<PlayerBlock> = {}): PlayerBlock {
    const set = playerSet();
    return {
        id: 1,
        position: 1,
        is_superset: false,
        is_ad_hoc: false,
        has_setup_after: false,
        has_setup_after_warm_up: false,
        exercises: [
            {
                id: 10,
                name: 'Squat',
                working_weight_kg: 100,
                prescribed_reps: 5,
                achievement_floor: null,
                progression_target: null,
                position: 0,
            },
        ],
        sets: [set],
        ...overrides,
    };
}

export function workoutPayload(overrides: Partial<WorkoutPayload> = {}): WorkoutPayload {
    return {
        id: '01TESTWORKOUTULID000000000',
        routine_name: 'Test',
        mode: 'standard',
        progression_style: 'straight_sets',
        progressive_mid_block: 'ask',
        status: 'in_progress',
        weight_unit: 'kg',
        blocks: [playerBlock()],
        ...overrides,
    };
}

export function plateProfile(overrides: Partial<PlateProfile> = {}): PlateProfile {
    return {
        name: 'Home',
        bars: [{ name: 'Olympic', weight_g: 20000, is_default: true }],
        plates: [
            { denomination_g: 20000, count: 2, colour: null },
            { denomination_g: 10000, count: 2, colour: null },
            { denomination_g: 5000, count: 2, colour: null },
            { denomination_g: 2500, count: 2, colour: null },
        ],
        ...overrides,
    };
}

export function block(overrides: Partial<Block> = {}): Block {
    return { ...emptyBlock(), ...overrides };
}
