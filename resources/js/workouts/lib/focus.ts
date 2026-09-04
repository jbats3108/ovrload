import type { Focus, PlayerBlock, SetupPhase } from '@/workouts/types';

export function setupKey(blockId: number, phase: SetupPhase, warmUpStepIndex?: number): string {
    if (phase === 'after_warm_up_step' && warmUpStepIndex !== undefined) {
        return `${blockId}:${phase}:${warmUpStepIndex}`;
    }

    return `${blockId}:${phase}`;
}

export type FlatSetEntry = {
    blockIndex: number;
    block: PlayerBlock;
    set: PlayerBlock['sets'][number];
};

export function flattenPlayerSets(blocks: PlayerBlock[]): FlatSetEntry[] {
    return blocks.flatMap((block, blockIndex) => block.sets.map((set) => ({ blockIndex, block, set })));
}

function warmUpStepIndexes(block: PlayerBlock): number[] {
    const indexes = new Set(block.sets.filter((s) => s.group_type === 'warm_up').map((s) => s.set_index));

    return [...indexes].sort((a, b) => a - b);
}

function warmUpHasSetupAfter(block: PlayerBlock, stepIndex: number): boolean {
    const set = block.sets.find((s) => s.group_type === 'warm_up' && s.set_index === stepIndex);

    return set?.has_setup_after ?? false;
}

/** True when later completed work proves this mid-warm-up setup was already acknowledged. */
function setupAfterWarmUpStepPassed(block: PlayerBlock, stepIndex: number): boolean {
    return block.sets.some(
        (set) => (set.group_type === 'warm_up' && set.set_index > stepIndex && set.completed) || (set.group_type === 'working' && set.completed),
    );
}

/** True when any working set in the block is logged — warm-up→work setup already passed. */
function setupAfterWarmUpPassed(block: PlayerBlock): boolean {
    return block.sets.some((set) => set.group_type === 'working' && set.completed);
}

/** True when a later block has logged work — between-block setup already passed. */
function setupAfterBlockPassed(blocks: PlayerBlock[], blockIndex: number): boolean {
    return blocks.slice(blockIndex + 1).some((block) => block.sets.some((set) => set.completed));
}

export function findFirstIncompleteFocus(blocks: PlayerBlock[], setupDone: Record<string, boolean>): Focus {
    for (let blockIndex = 0; blockIndex < blocks.length; blockIndex++) {
        const block = blocks[blockIndex];
        const stepIndexes = warmUpStepIndexes(block);
        const working = block.sets.filter((s) => s.group_type === 'working');
        const hasIncompleteWorking = working.some((s) => !s.completed);

        for (let i = 0; i < stepIndexes.length; i++) {
            const stepIndex = stepIndexes[i];
            const roundSets = block.sets.filter((s) => s.group_type === 'warm_up' && s.set_index === stepIndex);
            const incomplete = roundSets.find((s) => !s.completed);
            if (incomplete) {
                return { kind: 'set', blockIndex, setId: incomplete.id };
            }

            const isLastStep = i === stepIndexes.length - 1;
            if (
                !isLastStep &&
                warmUpHasSetupAfter(block, stepIndex) &&
                !setupDone[setupKey(block.id, 'after_warm_up_step', stepIndex)] &&
                !setupAfterWarmUpStepPassed(block, stepIndex)
            ) {
                return { kind: 'setup', blockIndex, phase: 'after_warm_up_step', warmUpStepIndex: stepIndex };
            }
        }

        if (
            block.has_setup_after_warm_up &&
            stepIndexes.length > 0 &&
            hasIncompleteWorking &&
            !setupDone[setupKey(block.id, 'after_warm_up')] &&
            !setupAfterWarmUpPassed(block)
        ) {
            return { kind: 'setup', blockIndex, phase: 'after_warm_up' };
        }

        const incompleteWorking = working.find((s) => !s.completed);
        if (incompleteWorking) {
            return { kind: 'set', blockIndex, setId: incompleteWorking.id };
        }

        if (
            block.has_setup_after &&
            blockIndex < blocks.length - 1 &&
            !setupDone[setupKey(block.id, 'after_block')] &&
            !setupAfterBlockPassed(blocks, blockIndex)
        ) {
            return { kind: 'setup', blockIndex, phase: 'after_block' };
        }
    }

    return { kind: 'done' };
}
