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

/** PC-only dev: localhost APP_URL and no LAN Vite host (avoids stale IPs + SSR timeouts). */
export function applyLaptopDevEnv(env: string): string {
    const port = readAppPort(env);
    const next = commentEnv(env, 'VITE_DEV_HOST');

    return upsertEnv(next, 'APP_URL', `http://localhost:${port}`);
}

/** Phone + PC: inject LAN IP for Vite HMR. APP_URL unchanged — UseRequestRootUrl follows browser Host. */
export function applyLanDevEnv(env: string, lanHost: string): string {
    return upsertEnv(env, 'VITE_DEV_HOST', lanHost);
}
