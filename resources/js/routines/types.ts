import type { WarmUpStep } from '@/settings/types';

export type { WarmUpStep } from '@/settings/types';

export type ExerciseOption = {
    id: number;
    name: string;
    primary_muscle_group: string;
    is_custom?: boolean;
};

export type MuscleGroupOption = {
    name: string;
    slug: string;
};

export type EquipmentOption = {
    value: string;
    label: string;
};

export type BlockExercise = {
    exercise_id: number | null;
    working_weight_kg: number;
    prescribed_reps: number;
    achievement_floor: number | null;
    progression_target: number | null;
    deload_exercise_id: number | null;
    deload_working_weight_kg: number | null;
};

export type DropsetSegment = {
    weight_kg: number;
};

export type DropsetRecipe = {
    set_index: number;
    segments: DropsetSegment[];
};

export type Block = {
    is_superset: boolean;
    has_setup_after: boolean;
    has_setup_after_warm_up: boolean;
    exercises: BlockExercise[];
    working: { set_count: number; rest_seconds: number; dropsets: DropsetRecipe[] };
    warm_up: { set_count: number; rest_seconds: number; steps: WarmUpStep[] };
};

export type RoutinePayload = {
    id: number;
    slug: string;
    name: string;
    deload_weight_factor: number;
    deload_reps_factor: number;
    deload_every_n: number;
    updated_at: string;
    blocks: Block[];
};

/** Dashboard / list row for a routine the user owns. */
export type Routine = {
    id: number;
    slug: string;
    name: string;
    deload_weight_factor?: number | null;
    deload_reps_factor?: number | null;
    deload_every_n?: number;
    can_start?: boolean;
    /** Finished standard workouts since this routine's last finished deload (all standards if never deloaded). */
    standards_since_deload?: number;
    /** False until this routine has at least one finished deload workout. */
    has_finished_deload?: boolean;
};

export type EditorDensity = 'desktop' | 'mobile';

export type DropsetEditorDensity = {
    card: string;
    setLabel: string;
    select: string;
    segmentRow: string;
    weightInput: string;
    addDropContainer: string;
    addDropButton: string;
    rackControls: string;
    rackLabel: string;
    rackInput: string;
    rackFillButton: string;
    rackFillLabel: string;
};

export type DeloadSettingsDensity = {
    fieldsGrid: string;
    fieldLabel: string;
    fieldTitle: string;
    fieldHint: string;
    input: string;
    weightHint: string;
    repsHint: string;
    everyHint: string;
};
