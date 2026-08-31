import type { ExerciseProfileWarmUpStep, WarmUpStep } from '@/settings/types';

export type WarmUpWeightMode = 'percent' | 'bar' | 'fixed';

export function resolvedWarmUpMode(step: Pick<WarmUpStep, 'mode'>): WarmUpWeightMode {
    return step.mode ?? 'percent';
}

export function formatWarmUpStepLabel(step: Pick<WarmUpStep, 'mode' | 'percent' | 'weight_kg' | 'reps'>): string {
    if (resolvedWarmUpMode(step) === 'bar') {
        return `bar×${step.reps}`;
    }

    if (resolvedWarmUpMode(step) === 'fixed') {
        return `${step.weight_kg ?? 0}kg×${step.reps}`;
    }

    return `${step.percent ?? 0}%×${step.reps}`;
}

export function formatProfileWarmUpSteps(steps: ExerciseProfileWarmUpStep[]): string {
    return steps.map(formatWarmUpStepLabel).join(', ');
}

export function isValidWarmUpStep(step: Pick<WarmUpStep, 'mode' | 'percent' | 'weight_kg' | 'reps'>): boolean {
    if (step.reps < 1) {
        return false;
    }

    if (resolvedWarmUpMode(step) === 'bar') {
        return true;
    }

    if (resolvedWarmUpMode(step) === 'fixed') {
        return (step.weight_kg ?? 0) >= 0.25;
    }

    return (step.percent ?? 0) >= 1;
}

export function setEditorWarmUpMode(step: Pick<WarmUpStep, 'mode' | 'percent' | 'weight_kg' | 'reps'>, mode: WarmUpWeightMode): void {
    step.mode = mode;

    if (mode === 'bar') {
        step.percent = undefined;
        step.weight_kg = undefined;
        return;
    }

    if (mode === 'fixed') {
        step.percent = undefined;
        if (step.weight_kg == null) {
            step.weight_kg = 60;
        }
        return;
    }

    step.weight_kg = undefined;
    if (step.percent == null) {
        step.percent = 50;
    }
}

/** Normalize a step for editor state without dropping bar vs percent mode. */
export function normalizeEditorWarmUpStep(
    step: Pick<WarmUpStep, 'mode' | 'percent' | 'weight_kg' | 'reps'> & { has_setup_after?: boolean },
): WarmUpStep {
    const mode = resolvedWarmUpMode(step);
    const reps = Number(step.reps ?? 5);

    if (mode === 'bar') {
        return {
            mode,
            percent: undefined,
            weight_kg: undefined,
            reps,
            has_setup_after: Boolean(step.has_setup_after),
        };
    }

    if (mode === 'fixed') {
        const weightKg = Number(step.weight_kg);

        return {
            mode,
            percent: undefined,
            weight_kg: Number.isFinite(weightKg) ? weightKg : 60,
            reps,
            has_setup_after: Boolean(step.has_setup_after),
        };
    }

    const percent = Number(step.percent);

    return {
        mode,
        percent: Number.isFinite(percent) ? percent : 0,
        weight_kg: undefined,
        reps,
        has_setup_after: Boolean(step.has_setup_after),
    };
}
