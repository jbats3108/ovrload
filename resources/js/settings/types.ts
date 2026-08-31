export type WarmUpWeightMode = 'percent' | 'bar' | 'fixed';

export type WarmUpStep = {
    mode?: WarmUpWeightMode;
    percent?: number;
    weight_kg?: number;
    reps: number;
    has_setup_after?: boolean;
};

export type WarmUpDefaultsScope = 'all_blocks' | 'first_block';

export type ExerciseProfileWarmUpStep = {
    mode?: WarmUpWeightMode;
    percent?: number;
    weight_kg?: number;
    reps: number;
};

export type ExerciseProfileAssignedRoutine = {
    name: string;
    slug: string;
};

export type ExerciseProfileOption = {
    id: number;
    slug: string;
    name: string;
    display_name: string;
    kind: 'custom' | 'preset';
    status: 'draft' | 'published' | 'archived';
    target_reps: number;
    floor: number;
    floor_override: number | null;
    working_rest_seconds: number;
    warm_up_steps: ExerciseProfileWarmUpStep[];
    recipe_fingerprint: string;
    exercise_fingerprint: string;
    shared_fingerprint: string;
    reference_count: number;
    stale_assignment_count: number;
    is_default: boolean;
    assigned_routines: ExerciseProfileAssignedRoutine[];
};

export type ExerciseProfilePage = {
    default_profile_id: number | null;
    profiles: ExerciseProfileOption[];
    archived_profiles: ExerciseProfileOption[];
};

export type PlateBar = {
    name: string;
    weight_g: number;
    is_default: boolean;
};

export type PlateRow = {
    denomination_g: number;
    count: number;
    colour: string | null;
};

export type PlateProfile = {
    name: string;
    bars: PlateBar[];
    plates: PlateRow[];
};
