import { clearPlayerClientState, loadPlayerClientState, savePlayerClientState } from '@/workouts/lib/playerClientState';
import { afterEach, describe, expect, it } from 'vitest';

describe('playerClientState', () => {
    afterEach(() => {
        clearPlayerClientState('w1');
    });

    it('round-trips setup and rest state', () => {
        savePlayerClientState('w1', {
            setupDone: { '10:after_warm_up': true },
            restEndsAt: 1_700_000_000_000,
        });

        expect(loadPlayerClientState('w1')).toEqual({
            setupDone: { '10:after_warm_up': true },
            restEndsAt: 1_700_000_000_000,
        });
    });

    it('returns empty state when nothing is stored', () => {
        expect(loadPlayerClientState('missing')).toEqual({ setupDone: {}, restEndsAt: null });
    });

    it('clears stored state', () => {
        savePlayerClientState('w1', { setupDone: { a: true }, restEndsAt: 1 });
        clearPlayerClientState('w1');
        expect(loadPlayerClientState('w1')).toEqual({ setupDone: {}, restEndsAt: null });
    });
});
