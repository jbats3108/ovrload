import type { ExerciseProfileWarmUpStep } from '@/settings/types';

export type MuscleGroupOption = {
    name: string;
    slug: string;
};

export type EquipmentOption = {
    value: string;
    label: string;
};

export type AdminExercise = {
    id: number;
    name: string;
    slug: string;
    equipment: string | null;
    primary_muscle_group: string;
    primary_muscle_group_slug: string;
    secondary_muscle_group: string | null;
    secondary_muscle_group_slug: string | null;
};

export type MuscleGroupRow = {
    id: number;
    name: string;
    slug: string;
};

export type InviteRow = {
    id: number;
    note: string | null;
    email: string | null;
    role: string;
    url: string;
    created_by: string | null;
    created_at: string | null;
    expires_at: string | null;
    used_at: string | null;
    used_by: string | null;
    revoked_at: string | null;
    usable: boolean;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string | null;
};

export type AdminExerciseProfile = {
    id: number;
    name: string;
    slug: string | null;
    status: 'draft' | 'published';
    target_reps: number;
    floor: number;
    floor_override: number | null;
    working_rest_seconds: number;
    warm_up_steps: ExerciseProfileWarmUpStep[];
    display_name: string;
};
