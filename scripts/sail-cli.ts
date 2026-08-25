/**
 * Shared process helpers for Sail host scripts (not run inside containers).
 */
import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');

export function sailBin(): string {
    const sail = resolve(root, 'vendor/bin/sail');

    if (!existsSync(sail)) {
        console.error('Missing vendor/bin/sail — run composer install.');
        process.exit(1);
    }

    return sail;
}

export function runSail(args: string[]): number {
    const result = spawnSync(sailBin(), args, { cwd: root, stdio: 'inherit', env: process.env });

    return result.status ?? 1;
}

export function waitForHttp(url: string, attempts = 30, delayMs = 1000): boolean {
    for (let i = 0; i < attempts; i++) {
        const probe = spawnSync('curl', ['-s', '-o', '/dev/null', '-w', '%{http_code}', '--connect-timeout', '2', url], {
            encoding: 'utf8',
            timeout: 5000,
        });
        const code = probe.stdout?.trim();

        if (code && code !== '000' && !code.startsWith('5')) {
            return true;
        }

        spawnSync('sleep', [`${delayMs / 1000}`]);
    }

    return false;
}

/**
 * Recreate Vite (and optional app) so Compose re-reads VITE_DEV_HOST from .env.
 * `sail restart` keeps the old container env and leaves public/hot on a stale host.
 */
export function recreateSailDevServices(services: string[] = ['vite', 'laravel.test']): number {
    return runSail(['up', '-d', '--wait', '--force-recreate', ...services]);
}
