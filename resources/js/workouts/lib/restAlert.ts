const REST_NOTIFICATION_TAG = 'ovrload-rest-end';
/** Whole seconds that get a short countdown tick (inclusive). */
export const REST_COUNTDOWN_FROM = 5;
const COUNTDOWN_BEEP_MS = 90;
const COUNTDOWN_VIBRATE_MS = 40;
const END_BEEP_MS = 480;
const END_VIBRATE_MS = 480;

let sharedAudioContext: AudioContext | null = null;

export type RestAlertOptions = {
    title?: string;
    body?: string;
    showNotification?: boolean;
};

/** @internal test helper */
export function resetRestAlertsForTests(): void {
    if (sharedAudioContext && typeof sharedAudioContext.close === 'function') {
        void sharedAudioContext.close();
    }
    sharedAudioContext = null;
}

export function canUseRestNotifications(): boolean {
    return typeof window !== 'undefined' && window.isSecureContext && 'Notification' in window;
}

export async function ensureRestNotificationPermission(): Promise<boolean> {
    if (!canUseRestNotifications()) {
        return false;
    }

    if (Notification.permission === 'granted') {
        return true;
    }

    if (Notification.permission === 'denied') {
        return false;
    }

    return (await Notification.requestPermission()) === 'granted';
}

function audioContextCtor(): typeof AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.AudioContext ?? (window as typeof window & { webkitAudioContext?: typeof AudioContext }).webkitAudioContext ?? null;
}

/** Call from a user gesture (tap) so rest-end audio can play later. */
export function primeRestAlerts(): void {
    const AudioContextCtor = audioContextCtor();
    if (!AudioContextCtor) {
        return;
    }

    if (!sharedAudioContext) {
        sharedAudioContext = new AudioContextCtor();
    }

    if (sharedAudioContext.state === 'suspended') {
        void sharedAudioContext.resume();
    }
}

function playTone(frequencyHz: number, durationMs: number, gainValue = 0.08): boolean {
    const AudioContextCtor = audioContextCtor();
    if (!AudioContextCtor) {
        return false;
    }

    if (!sharedAudioContext || sharedAudioContext.state !== 'running') {
        return false;
    }

    const context = sharedAudioContext;
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequencyHz;
    gain.gain.value = gainValue;

    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start();

    window.setTimeout(() => {
        oscillator.stop();
    }, durationMs);

    return true;
}

function vibratePattern(pattern: number | number[]): void {
    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
        navigator.vibrate(pattern);
    }
}

export function playRestEndSound(): boolean {
    return playTone(880, END_BEEP_MS);
}

/** Short tick for seconds 5…1 before rest ends. */
export function playRestCountdownBeep(secondsLeft: number): boolean {
    if (!shouldBeepRestCountdown(secondsLeft)) {
        return false;
    }

    return playTone(760, COUNTDOWN_BEEP_MS, 0.06);
}

export function shouldBeepRestCountdown(secondsLeft: number): boolean {
    return secondsLeft >= 1 && secondsLeft <= REST_COUNTDOWN_FROM;
}

export function vibrateRestCountdown(): void {
    vibratePattern(COUNTDOWN_VIBRATE_MS);
}

export function vibrateRestEnd(): void {
    vibratePattern(END_VIBRATE_MS);
}

/** Short beep + haptic for one countdown second (no-op outside 5…1). */
export function notifyRestCountdown(secondsLeft: number): void {
    if (!shouldBeepRestCountdown(secondsLeft)) {
        return;
    }

    playRestCountdownBeep(secondsLeft);
    vibrateRestCountdown();
}

export function showRestEndNotification(options: RestAlertOptions = {}): void {
    if (!canUseRestNotifications() || Notification.permission !== 'granted') {
        return;
    }

    const title = options.title ?? 'Rest over';
    const body = options.body ?? 'Time for the next set.';

    try {
        new Notification(title, {
            body,
            tag: REST_NOTIFICATION_TAG,
            silent: false,
        });
    } catch {
        // Safari/iOS may throw when permission state is stale.
    }
}

export function notifyRestEnded(options: RestAlertOptions = {}): void {
    const playedSound = playRestEndSound();
    vibrateRestEnd();

    if (options.showNotification !== false && Notification.permission === 'granted' && (!playedSound || document.hidden)) {
        showRestEndNotification(options);
    }
}

/** User-gesture entry point: prime audio without prompting for notification access. */
export function prepareRestAlerts(): void {
    primeRestAlerts();
}
