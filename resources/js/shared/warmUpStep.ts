import type { ExerciseProfileWarmUpStep, WarmUpStep } from '@/settings/types';

export type WarmUpWeightMode = 'percent' | 'bar';

export function resolvedWarmUpMode(step: Pick<WarmUpStep, 'mode'>): WarmUpWeightMode {
    return step.mode ?? 'percent';
}

export function formatWarmUpStepLabel(step: Pick<WarmUpStep, 'mode' | 'percent' | 'reps'>): string {
    if (resolvedWarmUpMode(step) === 'bar') {
        return `bar×${step.reps}`;
    }

    return `${step.percent ?? 0}%×${step.reps}`;
}

export function formatProfileWarmUpSteps(steps: ExerciseProfileWarmUpStep[]): string {
    return steps.map(formatWarmUpStepLabel).join(', ');
}

export function isValidWarmUpStep(step: Pick<WarmUpStep, 'mode' | 'percent' | 'reps'>): boolean {
    if (step.reps < 1) {
        return false;
    }

    if (resolvedWarmUpMode(step) === 'bar') {
        return true;
    }

    return (step.percent ?? 0) >= 1;
}
