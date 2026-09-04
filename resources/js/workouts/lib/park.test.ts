import { playerBlock, playerSet } from '@/test/factories';
import { canParkBlockForLater, parkedIncompleteCount, skipGroupLabel } from '@/workouts/lib/park';
import { describe, expect, it } from 'vitest';

describe('canParkBlockForLater', () => {
    it('allows parking an untouched block when a later incomplete group exists', () => {
        const blocks = [
            playerBlock({ id: 1, position: 1, sets: [playerSet({ id: 1, completed: false })] }),
            playerBlock({ id: 2, position: 2, sets: [playerSet({ id: 2, completed: false })] }),
        ];
        expect(canParkBlockForLater(blocks[0], blocks)).toBe(true);
    });

    it('rejects when any set is logged', () => {
        const blocks = [
            playerBlock({
                id: 1,
                position: 1,
                sets: [playerSet({ id: 1, completed: true }), playerSet({ id: 2, set_index: 1, completed: false })],
            }),
            playerBlock({ id: 2, position: 2, sets: [playerSet({ id: 3, completed: false })] }),
        ];
        expect(canParkBlockForLater(blocks[0], blocks)).toBe(false);
    });

    it('rejects the last incomplete group', () => {
        const blocks = [playerBlock({ id: 1, position: 1, sets: [playerSet({ id: 1, completed: false })] })];
        expect(canParkBlockForLater(blocks[0], blocks)).toBe(false);
    });
});

describe('parkedIncompleteCount', () => {
    it('counts parked blocks with incompletes', () => {
        const blocks = [
            playerBlock({ id: 1, position: 1, is_parked: true, sets: [playerSet({ id: 1, completed: false })] }),
            playerBlock({ id: 2, position: 2, is_parked: true, sets: [playerSet({ id: 2, completed: true })] }),
            playerBlock({ id: 3, position: 3, is_parked: false, sets: [playerSet({ id: 3, completed: false })] }),
        ];
        expect(parkedIncompleteCount(blocks)).toBe(1);
    });
});

describe('skipGroupLabel', () => {
    it('uses Skip group when untouched', () => {
        expect(skipGroupLabel(playerBlock({ sets: [playerSet({ completed: false })] }))).toBe('Skip group');
    });

    it('uses Skip rest of group when started', () => {
        expect(
            skipGroupLabel(
                playerBlock({
                    sets: [playerSet({ id: 1, completed: true }), playerSet({ id: 2, set_index: 1, completed: false })],
                }),
            ),
        ).toBe('Skip rest of group');
    });
});
