import { playerBlock, playerSet } from '@/test/factories';
import { findFirstIncompleteFocus, flattenPlayerSets, setupKey } from '@/workouts/lib/focus';
import { describe, expect, it } from 'vitest';

describe('setupKey', () => {
    it('combines block id and phase', () => {
        expect(setupKey(5, 'after_block')).toBe('5:after_block');
    });
});

describe('flattenPlayerSets', () => {
    it('flattens sets with block index', () => {
        const blocks = [
            playerBlock({
                sets: [playerSet({ id: 1 }), playerSet({ id: 2, set_index: 1 })],
            }),
        ];
        expect(flattenPlayerSets(blocks)).toHaveLength(2);
    });
});

describe('findFirstIncompleteFocus', () => {
    it('returns first incomplete warm-up', () => {
        const blocks = [
            playerBlock({
                sets: [playerSet({ id: 1, group_type: 'warm_up', completed: false }), playerSet({ id: 2, group_type: 'working', completed: false })],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({ kind: 'set', blockIndex: 0, setId: 1 });
    });

    it('returns setup between warm-up steps', () => {
        const blocks = [
            playerBlock({
                sets: [
                    playerSet({ id: 1, group_type: 'warm_up', set_index: 0, completed: true, has_setup_after: true }),
                    playerSet({ id: 2, group_type: 'warm_up', set_index: 1, completed: false }),
                    playerSet({ id: 3, group_type: 'working', completed: false }),
                ],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({
            kind: 'setup',
            blockIndex: 0,
            phase: 'after_warm_up_step',
            warmUpStepIndex: 0,
        });
    });

    it('returns setup between warm-up and working', () => {
        const blocks = [
            playerBlock({
                has_setup_after_warm_up: true,
                sets: [playerSet({ id: 1, group_type: 'warm_up', completed: true }), playerSet({ id: 2, group_type: 'working', completed: false })],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({
            kind: 'setup',
            blockIndex: 0,
            phase: 'after_warm_up',
        });
    });

    it('skips warm-up setup when a working set is already logged (resume)', () => {
        const blocks = [
            playerBlock({
                has_setup_after_warm_up: true,
                sets: [
                    playerSet({ id: 1, group_type: 'warm_up', completed: true }),
                    playerSet({ id: 2, group_type: 'working', completed: true }),
                    playerSet({ id: 3, group_type: 'working', set_index: 1, completed: false }),
                ],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({ kind: 'set', blockIndex: 0, setId: 3 });
    });

    it('skips between-block setup when a later block already has logged work', () => {
        const blocks = [
            playerBlock({
                id: 10,
                has_setup_after: true,
                sets: [playerSet({ id: 1, completed: true })],
            }),
            playerBlock({
                id: 11,
                sets: [playerSet({ id: 2, completed: true }), playerSet({ id: 3, set_index: 1, completed: false })],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({ kind: 'set', blockIndex: 1, setId: 3 });
    });

    it('returns done when everything complete', () => {
        const blocks = [
            playerBlock({
                sets: [playerSet({ id: 1, completed: true })],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({ kind: 'done' });
    });

    it('skips setup after block on the final block', () => {
        const blocks = [
            playerBlock({
                has_setup_after: true,
                sets: [playerSet({ id: 1, completed: true })],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({ kind: 'done' });
    });

    it('skips parked blocks and focuses the next incomplete group', () => {
        const blocks = [
            playerBlock({
                id: 1,
                position: 1,
                is_parked: true,
                sets: [playerSet({ id: 1, completed: false })],
            }),
            playerBlock({
                id: 2,
                position: 2,
                sets: [playerSet({ id: 2, completed: false })],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({ kind: 'set', blockIndex: 1, setId: 2 });
    });

    it('returns done when only parked incompletes remain', () => {
        const blocks = [
            playerBlock({
                id: 1,
                position: 1,
                is_parked: true,
                sets: [playerSet({ id: 1, completed: false })],
            }),
        ];
        expect(findFirstIncompleteFocus(blocks, {})).toEqual({ kind: 'done' });
    });
});
