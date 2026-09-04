import type { PlayerBlock } from '@/workouts/types';

export function blockHasLoggedSet(block: PlayerBlock): boolean {
    return block.sets.some((set) => set.completed);
}

export function blockHasIncompleteSet(block: PlayerBlock): boolean {
    return block.sets.some((set) => !set.completed);
}

export function parkedIncompleteCount(blocks: PlayerBlock[]): number {
    return blocks.filter((block) => block.is_parked && blockHasIncompleteSet(block)).length;
}

/** Untouched block that, if parked, would leave another non-parked incomplete group after it. */
export function canParkBlockForLater(block: PlayerBlock, blocks: PlayerBlock[]): boolean {
    if (block.is_parked || blockHasLoggedSet(block) || !blockHasIncompleteSet(block)) {
        return false;
    }

    const index = blocks.findIndex((candidate) => candidate.id === block.id);
    if (index < 0) {
        return false;
    }

    // After parking this block, Play must still have a later non-parked incomplete group.
    return blocks.some((other, otherIndex) => otherIndex > index && !other.is_parked && blockHasIncompleteSet(other));
}

export function skipGroupLabel(block: PlayerBlock): string {
    return blockHasLoggedSet(block) ? 'Skip rest of group' : 'Skip group';
}

export function skipGroupConfirmTitle(block: PlayerBlock): string {
    return blockHasLoggedSet(block) ? 'Skip rest of this group?' : 'Skip this group?';
}
