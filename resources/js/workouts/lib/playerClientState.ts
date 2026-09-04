export type PlayerClientState = {
    setupDone: Record<string, boolean>;
    /** Epoch ms when the current rest period ends; null when not resting. */
    restEndsAt: number | null;
};

function storageKey(workoutId: string): string {
    return `ovrload:play:${workoutId}`;
}

export function loadPlayerClientState(workoutId: string): PlayerClientState {
    try {
        const raw = sessionStorage.getItem(storageKey(workoutId));
        if (!raw) {
            return { setupDone: {}, restEndsAt: null };
        }

        const parsed = JSON.parse(raw) as Partial<PlayerClientState>;
        return {
            setupDone: parsed.setupDone && typeof parsed.setupDone === 'object' ? parsed.setupDone : {},
            restEndsAt: typeof parsed.restEndsAt === 'number' ? parsed.restEndsAt : null,
        };
    } catch {
        return { setupDone: {}, restEndsAt: null };
    }
}

export function savePlayerClientState(workoutId: string, state: PlayerClientState): void {
    try {
        sessionStorage.setItem(storageKey(workoutId), JSON.stringify(state));
    } catch {
        // Private mode / quota — resume still works for logged sets; setup/rest may replay.
    }
}

export function clearPlayerClientState(workoutId: string): void {
    try {
        sessionStorage.removeItem(storageKey(workoutId));
    } catch {
        // ignore
    }
}
