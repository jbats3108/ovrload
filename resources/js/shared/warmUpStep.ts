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

/** Normalize a step for editor state without dropping bar vs percent mode. */
export function normalizeEditorWarmUpStep(step: Pick<WarmUpStep, 'mode' | 'percent' | 'reps'> & { has_setup_after?: boolean }): WarmUpStep {
    const mode = resolvedWarmUpMode(step);
    const reps = Number(step.reps ?? 5);

    if (mode === 'bar') {
        return {
            mode,
            percent: undefined,
            reps,
            has_setup_after: Boolean(step.has_setup_after),
        };
    }

    const percent = Number(step.percent);

    return {
        mode,
        percent: Number.isFinite(percent) ? percent : 0,
        reps,
        has_setup_after: Boolean(step.has_setup_after),
    };
}
