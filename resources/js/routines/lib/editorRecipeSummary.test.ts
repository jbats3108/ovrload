import { emptyBlock, emptyExercise } from '@/routines/lib/blocks';
import {
    blockSharedRecipeIsCustom,
    exerciseRecipeIsCustom,
    formatBlockSharedRecipeSummary,
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

    it('formats shared rest and warm-up for the collapsed summary', () => {
        const block = emptyBlock({ seedWarmUp: false });
        block.working.rest_seconds = 180;
        block.warm_up.steps = [
            { mode: 'percent', percent: 40, reps: 5 },
            { mode: 'percent', percent: 60, reps: 3 },
        ];
        block.warm_up.set_count = 2;
        block.warm_up.rest_seconds = 60;

        expect(formatBlockSharedRecipeSummary(block)).toBe('Rest 3m · 40%×5, 60%×3 · WU rest 1m');
    });
});
