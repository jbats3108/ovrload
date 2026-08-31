import {
    achievementFloorForSave,
    applyProfileToBlock,
    applyProfileToSupersetExercise,
    coerceProfileId,
    markExerciseProfileCustom,
    markSharedProfileCustom,
    normalizeExerciseForEditor,
    profileMatchesExerciseAssignment,
    profileMatchesSharedAssignment,
} from '@/routines/lib/exerciseProfiles';
import type { ExerciseProfileOption } from '@/settings/types';
import { block } from '@/test/factories';
import { describe, expect, it } from 'vitest';

const strength: ExerciseProfileOption = {
    id: 1,
    slug: 'preset-strength',
    name: 'Strength',
    display_name: 'OVRLOAD Strength',
    kind: 'preset',
    status: 'published',
    target_reps: 6,
    floor: 4,
    floor_override: null,
    working_rest_seconds: 180,
    warm_up_steps: [
        { mode: 'bar', reps: 10 },
        { mode: 'percent', percent: 50, reps: 5 },
        { mode: 'percent', percent: 75, reps: 3 },
        { mode: 'percent', percent: 90, reps: 1 },
    ],
    recipe_fingerprint: 'recipe-strength',
    exercise_fingerprint: 'exercise-strength',
    shared_fingerprint: 'shared-strength',
    reference_count: 0,
    stale_assignment_count: 0,
    is_default: false,
};

const hypertrophy: ExerciseProfileOption = {
    ...strength,
    id: 2,
    slug: 'preset-hypertrophy',
    name: 'Hypertrophy',
    display_name: 'OVRLOAD Hypertrophy',
    target_reps: 10,
    floor: 8,
    working_rest_seconds: 90,
    warm_up_steps: [
        { percent: 50, reps: 10 },
        { percent: 80, reps: 5 },
    ],
    recipe_fingerprint: 'recipe-hypertrophy',
    exercise_fingerprint: 'exercise-hypertrophy',
    shared_fingerprint: 'shared-hypertrophy',
    is_default: false,
};

describe('exercise profile helpers', () => {
    it('applies a complete profile to a single block', () => {
        const current = block({
            warm_up: {
                set_count: 1,
                rest_seconds: 60,
                steps: [{ percent: 40, reps: 5, has_setup_after: true }],
            },
        });

        applyProfileToBlock(current, strength);

        expect(current.exercises[0]).toMatchObject({
            prescribed_reps: 6,
            achievement_floor: null,
            exercise_profile_id: 1,
            exercise_profile_fingerprint: 'recipe-strength',
        });
        expect(current.working.rest_seconds).toBe(180);
        expect(current.shared_profile_id).toBe(1);
        expect(current.shared_profile_fingerprint).toBe('shared-strength');
        expect(current.warm_up.steps).toEqual([
            { mode: 'bar', reps: 10, has_setup_after: true },
            { mode: 'percent', percent: 50, reps: 5, has_setup_after: false },
            { mode: 'percent', percent: 75, reps: 3, has_setup_after: false },
            { mode: 'percent', percent: 90, reps: 1, has_setup_after: false },
        ]);
    });

    it('applies only exercise values when changing one Superset profile', () => {
        const current = block({
            is_superset: true,
            exercises: [{ ...block().exercises[0] }, { ...block().exercises[0] }],
        });
        current.shared_profile_id = strength.id;
        current.shared_profile_fingerprint = strength.shared_fingerprint;

        applyProfileToSupersetExercise(current, 1, hypertrophy);

        expect(current.exercises[0].prescribed_reps).toBe(6);
        expect(current.exercises[1]).toMatchObject({
            prescribed_reps: 10,
            achievement_floor: null,
            exercise_profile_id: 2,
            exercise_profile_fingerprint: 'exercise-hypertrophy',
        });
        expect(current.shared_profile_id).toBe(1);
        expect(current.working.rest_seconds).toBe(120);
    });

    it('marks profile-owned values as custom without changing their values', () => {
        const current = block();
        current.exercises[0].exercise_profile_id = strength.id;
        current.exercises[0].exercise_profile_fingerprint = strength.recipe_fingerprint;
        current.shared_profile_id = strength.id;
        current.shared_profile_fingerprint = strength.shared_fingerprint;

        markExerciseProfileCustom(current.exercises[0]);
        markSharedProfileCustom(current);

        expect(current.exercises[0].prescribed_reps).toBe(6);
        expect(current.exercises[0].exercise_profile_id).toBeNull();
        expect(current.shared_profile_id).toBeNull();
    });

    it('detects stale exercise and shared assignments from fingerprints', () => {
        const current = block();
        current.shared_profile_id = strength.id;
        current.shared_profile_fingerprint = 'old-shared';
        current.exercises[0].exercise_profile_id = strength.id;
        current.exercises[0].exercise_profile_fingerprint = 'old-recipe';

        expect(profileMatchesSharedAssignment(current, strength)).toBe(false);
        expect(profileMatchesExerciseAssignment(current.exercises[0], strength, false, true)).toBe(false);
    });

    it('uses the exercise fingerprint when the block shared profile belongs to another profile', () => {
        const current = block();
        current.shared_profile_id = strength.id;
        current.shared_profile_fingerprint = strength.shared_fingerprint;
        current.exercises[0].exercise_profile_id = hypertrophy.id;
        current.exercises[0].exercise_profile_fingerprint = hypertrophy.exercise_fingerprint;

        expect(profileMatchesExerciseAssignment(current.exercises[0], hypertrophy, false, false)).toBe(true);
        expect(profileMatchesExerciseAssignment(current.exercises[0], hypertrophy, false, true)).toBe(false);
    });

    it('uses the exercise fingerprint for supersets even when shared warm-up data exists', () => {
        const current = block({ is_superset: true, exercises: [{ ...block().exercises[0] }, { ...block().exercises[0] }] });
        current.shared_profile_id = strength.id;
        current.shared_profile_fingerprint = strength.shared_fingerprint;
        current.exercises[0].exercise_profile_id = strength.id;
        current.exercises[0].exercise_profile_fingerprint = strength.exercise_fingerprint;

        expect(profileMatchesExerciseAssignment(current.exercises[0], strength, true, true)).toBe(true);
        expect(profileMatchesExerciseAssignment(current.exercises[0], strength, false, true)).toBe(false);
    });

    it('coerces string profile ids from select inputs', () => {
        expect(coerceProfileId('2')).toBe(2);
        expect(coerceProfileId(2)).toBe(2);
        expect(coerceProfileId('')).toBeNull();
        expect(coerceProfileId(undefined)).toBeNull();
    });

    it('clears stored floors when a profile-derived floor is loaded in the editor', () => {
        const exercise = normalizeExerciseForEditor({
            exercise_id: 1,
            working_weight_kg: 30,
            prescribed_reps: 10,
            achievement_floor: 8,
            floor_is_derived: true,
            progression_target: null,
            deload_exercise_id: null,
            deload_working_weight_kg: null,
            exercise_profile_id: 2,
            exercise_profile_fingerprint: 'recipe-hypertrophy',
        });

        expect(exercise.achievement_floor).toBeNull();
        expect(achievementFloorForSave(exercise)).toBeNull();
    });
});
