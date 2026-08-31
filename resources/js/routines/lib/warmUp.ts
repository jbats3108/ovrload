import type { Block, WarmUpStep } from '@/routines/types';
import { formatWarmUpStepLabel, isValidWarmUpStep, resolvedWarmUpMode } from '@/shared/warmUpStep';

type ParsedWarmUpStep = { mode: 'bar'; reps: number } | { mode: 'percent'; percent: number; reps: number };

export function warmUpText(block: Block): string {
    return block.warm_up.steps.map(formatWarmUpStepLabel).join(', ');
}

export function formatWarmUpStep(step: Pick<WarmUpStep, 'mode' | 'percent' | 'reps'>): string {
    return formatWarmUpStepLabel(step);
}

export function syncWarmUpMeta(block: Block): void {
    block.warm_up.set_count = block.warm_up.steps.length;
    if (block.warm_up.steps.length === 0) {
        block.has_setup_after_warm_up = false;
    }
}

function normalizeWarmUpStep(step: WarmUpStep): WarmUpStep {
    const mode = resolvedWarmUpMode(step);

    return {
        mode,
        percent: mode === 'percent' ? step.percent : undefined,
        reps: step.reps,
        has_setup_after: step.has_setup_after ?? false,
    };
}

/** Compact editor string: `bar×10, 40%×5, 60%×3` (also accepts legacy `40, 60, 80`). */
export function setWarmUpText(block: Block, value: string): void {
    const previousFlags = block.warm_up.steps.map((s) => s.has_setup_after ?? false);
    block.warm_up.steps = value
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean)
        .map((part) => {
            const barWithReps = part.match(/^bar\s*[x×]\s*(\d+)$/i);
            if (barWithReps) {
                return { mode: 'bar' as const, reps: parseInt(barWithReps[1], 10) };
            }
            const withReps = part.match(/^(\d+)\s*%?\s*[x×]\s*(\d+)$/i);
            if (withReps) {
                return { mode: 'percent' as const, percent: parseInt(withReps[1], 10), reps: parseInt(withReps[2], 10) };
            }
            const percentOnly = part.match(/^(\d+)\s*%?$/);
            if (percentOnly) {
                return { mode: 'percent' as const, percent: parseInt(percentOnly[1], 10), reps: 5 };
            }
            return null;
        })
        .filter((s): s is ParsedWarmUpStep => s !== null && isValidWarmUpStep(s))
        .map((step, index) => normalizeWarmUpStep({ ...step, has_setup_after: previousFlags[index] ?? false }));
    syncWarmUpMeta(block);
}

export function addWarmUpStep(block: Block): void {
    block.warm_up.steps.push({ mode: 'percent', percent: 50, reps: 5, has_setup_after: false });
    syncWarmUpMeta(block);
}

export function removeWarmUpStep(block: Block, index: number): void {
    block.warm_up.steps.splice(index, 1);
    syncWarmUpMeta(block);
}

export function clearWarmUp(block: Block): void {
    block.warm_up.steps = [];
    syncWarmUpMeta(block);
}

/** Drop steps that would fail server validation (cleared/zero mobile inputs). */
export function sanitizeWarmUpStepsForSave(steps: WarmUpStep[]): WarmUpStep[] {
    return steps.filter(isValidWarmUpStep).map((step) => normalizeWarmUpStep(step));
}

export function canSetupAfterWarmUpStep(block: Block, stepIndex: number): boolean {
    return stepIndex < block.warm_up.steps.length - 1;
}
