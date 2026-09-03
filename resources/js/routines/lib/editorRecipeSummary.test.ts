import { emptyBlock, emptyExercise } from '@/routines/lib/blocks';
import {
    blockSharedRecipeIsCustom,
    exerciseRecipeIsCustom,
    formatBlockRestSummary,
    formatBlockSharedRecipeSummary,
    formatBlockWarmUpSummary,
    formatExerciseTargetFloorSummary,
} from '@/routines/lib/editorRecipeSummary';
import { describe, expect, it } from 'vitest';

describe('editorRecipeSummary', () => {
    it('treats a null exercise profile as Custom', () => {
        expect(exerciseRecipeIsCustom(emptyExercise())).toBe(true);
        expect(exerciseRecipeIsCustom({ ...emptyExercise(), exercise_profile_id: 3 })).toBe(false);
    });

    it('treats a null shared profile as Custom', () => {
        expect(blockSharedRecipeIsCustom(emptyBlock())).toBe(true);
        expect(blockSharedRecipeIsCustom({ ...emptyBlock(), shared_profile_id: 1 })).toBe(false);
    });

    it('formats target and floor with a placeholder when floor is blank', () => {
        const exercise = { ...emptyExercise(), prescribed_reps: 6, achievement_floor: null };
        expect(formatExerciseTargetFloorSummary(exercise, '4')).toBe('Target 6 · Floor 4');
        expect(formatExerciseTargetFloorSummary({ ...exercise, achievement_floor: 5 }, '4')).toBe('Target 6 · Floor 5');
    });

    it('formats rest and warm-up separately for desktop columns', () => {
        const block = emptyBlock({ seedWarmUp: false });
        block.working.rest_seconds = 180;
        block.warm_up.steps = [
            { mode: 'bar', reps: 10 },
            { mode: 'percent', percent: 50, reps: 5 },
            { mode: 'percent', percent: 75, reps: 3 },
            { mode: 'percent', percent: 90, reps: 1 },
        ];
        block.warm_up.set_count = 4;
        block.warm_up.rest_seconds = 60;

        expect(formatBlockRestSummary(block)).toBe('3m');
        expect(formatBlockWarmUpSummary(block)).toEqual({
            steps: ['bar×10', '50%×5', '75%×3', '90%×1'],
            rest: '1m',
        });
        expect(formatBlockSharedRecipeSummary(block)).toBe('Rest 3m · bar×10, 50%×5, 75%×3, 90%×1 · WU rest 1m');
    });
});
