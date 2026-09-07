import type { FlatSetEntry } from '@/workouts/lib/focus';
import { historyBlockTitle } from '@/workouts/lib/historyDisplay';
import type { Focus, PlayerBlock } from '@/workouts/types';

export function groupLabel(type: string): string {
    return type === 'warm_up' ? 'Warm-up' : 'Working';
}

export function formatRestSeconds(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const r = seconds % 60;
    return m > 0 ? `${m}:${r.toString().padStart(2, '0')}` : `${r}s`;
}

export function workoutProgressLabel(flatSets: FlatSetEntry[]): string {
    const total = flatSets.length;
    const done = flatSets.filter(({ set }) => set.completed).length;
    return `${done}/${total}`;
}

export function setupHintText(focus: Focus, block: PlayerBlock | null): string {
    if (focus.kind !== 'setup' || !block) {
        return '';
    }

    const name = historyBlockTitle(block);

    if (focus.phase === 'before_circuit') {
        return `Prepare circuit stations — ${name}`;
    }
    if (focus.phase === 'after_warm_up') {
        return `${name} — before working sets`;
    }
    if (focus.phase === 'after_warm_up_step') {
        const stepNum = (focus.warmUpStepIndex ?? 0) + 1;

        return `${name} — after warm-up ${stepNum}`;
    }

    return `After ${name}`;
}
