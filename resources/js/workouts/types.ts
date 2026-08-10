import type { PlateStack } from '@/lib/plateCalculator';

export type { PlateProfile } from '@/settings/types';

export type PlayerSetSegment = {
    position: number;
    weight_kg: number;
};

export type PlayerSet = {
    id: number;
    workout_block_exercise_id: number;
    exercise_name: string;
    equipment: string | null;
    set_index: number;
    group_type: 'warm_up' | 'working';
    target_weight_kg: number | null;
    target_reps: number | null;
    logged_weight_kg: number | null;
    plate_stack: PlateStack | null;
    logged_reps: number | null;
    completed: boolean;
    rest_seconds: number;
    has_setup_after: boolean;
    is_dropset: boolean;
    segments: PlayerSetSegment[];
};

export type PlayerBlockExercise = {
    id: number;
    name: string;
    equipment?: string | null;
    working_weight_kg: number;
    prescribed_reps: number;
    achievement_floor: number | null;
    progression_target: number | null;
    position: number;
};

export type PlayerBlock = {
    id: number;
    position: number;
    is_superset: boolean;
    has_setup_after: boolean;
    has_setup_after_warm_up: boolean;
    exercises: PlayerBlockExercise[];
    sets: PlayerSet[];
};

export type WorkoutPayload = {
    id: string;
    routine_name: string;
    mode: string;
    status: string;
    weight_unit: string;
    blocks: PlayerBlock[];
};

export type SetupPhase = 'after_warm_up' | 'after_block' | 'after_warm_up_step';

export type Focus =
    | { kind: 'set'; blockIndex: number; setId: number }
    | { kind: 'setup'; blockIndex: number; phase: SetupPhase; warmUpStepIndex?: number }
    | { kind: 'done' };

export type Bump = {
    routine_block_exercise_id: number;
    exercise_name: string;
    from_weight_g: number;
    to_weight_g: number;
};

export type UndoBump = {
    bump_record_id: number;
    routine_block_exercise_id: number;
    exercise_name: string;
    from_weight_g: number;
    to_weight_g: number;
};

export type HistoryWorkout = {
    id: string;
    routine_name: string;
    routine_id: number;
    mode: string;
    finished_at: string;
};

export type HistoricalCreateSegment = {
    weight_kg: number;
};

export type HistoricalCreateSet = {
    exercise_position: number;
    exercise_name: string;
    set_index: number;
    is_dropset: boolean;
    weight_kg: number | null;
    reps: number;
    segments: HistoricalCreateSegment[];
};

export type HistoricalCreateWarmUp = {
    exercise_position: number;
    exercise_name: string;
    set_index: number;
    percent_of_working: number;
    reps: number;
};

export type HistoricalCreateExercise = {
    position: number;
    name: string;
    equipment: string | null;
    working_weight_kg: number;
    prescribed_reps: number;
    deload_name: string | null;
    deload_equipment: string | null;
    deload_working_weight_kg: number | null;
};

export type HistoricalCreateBlock = {
    position: number;
    is_superset: boolean;
    exercises: HistoricalCreateExercise[];
    working_set_count: number;
    working_sets: HistoricalCreateSet[];
    warm_ups: HistoricalCreateWarmUp[];
};

export type HistoricalCreateForm = {
    routine_slug: string;
    routine_name: string;
    deload_weight_factor: number;
    deload_reps_factor: number;
    blocks: HistoricalCreateBlock[];
};

export type InProgressWorkout = {
    id: string;
    routine_name: string;
    mode: string;
};
