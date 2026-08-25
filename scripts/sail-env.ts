/**
 * Shared .env helpers for Sail startup scripts (laptop-first vs LAN phone mode).
 */

export function readEnvValue(env: string, key: string): string | null {
    const match = env.match(new RegExp(`^${key}=(.*)$`, 'm'));
    const value = match?.[1]?.trim();

    return value ? value : null;
}

export function upsertEnv(content: string, key: string, value: string): string {
    const line = `${key}=${value}`;
    const pattern = new RegExp(`^#?\\s*${key}=.*$`, 'm');

    if (pattern.test(content)) {
        return content.replace(pattern, line);
    }

    return `${content.trimEnd()}\n${line}\n`;
}

export function commentEnv(content: string, key: string): string {
    const pattern = new RegExp(`^#?\\s*${key}=.*$`, 'm');

    if (pattern.test(content)) {
        return content.replace(pattern, `# ${key}=`);
    }

    return content;
}

export function readAppPort(env: string): string {
    const match = env.match(/^APP_PORT=(.*)$/m);

    return (match?.[1] ?? '8000').trim() || '8000';
}

/**
 * Pick a LAN host for phone Vite HMR.
 * Prefer an explicit process override; otherwise use a fresh OS detect.
 * Never reuse a stale VITE_DEV_HOST from .env — Wi‑Fi IPs change often.
 */
export function resolveLanHost(processOverride: string | null | undefined, detected: string | null): string | null {
    const override = processOverride?.trim();

    if (override) {
        return override;
    }

    return detected;
}

/** PC-only dev: localhost APP_URL and no LAN Vite host (avoids stale IPs + SSR timeouts). */
export function applyLaptopDevEnv(env: string): string {
    const port = readAppPort(env);
    const next = commentEnv(env, 'VITE_DEV_HOST');

    return upsertEnv(next, 'APP_URL', `http://localhost:${port}`);
}

/**
 * Phone + PC: inject LAN IP for Vite HMR.
 * Keep APP_URL on localhost — UseRequestRootUrl follows the browser Host on each request.
 */
export function applyLanDevEnv(env: string, lanHost: string): string {
    const port = readAppPort(env);
    const withHost = upsertEnv(env, 'VITE_DEV_HOST', lanHost);

    return upsertEnv(withHost, 'APP_URL', `http://localhost:${port}`);
}
