/**
 * Switch an already-running Sail stack to phone-reachable LAN Vite HMR (or back to laptop-only).
 *
 * Usage (host, not inside Sail):
 *   npm run sail:lan
 *   npm run sail:localhost
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { detectLanIpv4 } from '../vite/detectLanHost.ts';
import { applyLanDevEnv, applyLaptopDevEnv, readAppPort, readEnvValue } from './sail-env.ts';

const root = resolve(import.meta.dirname, '..');
const envPath = resolve(root, '.env');
const toLocalhost = process.argv.includes('--localhost');
const dryRun = process.argv.includes('--dry-run');

function restartSailServices(): void {
    if (dryRun) {
        return;
    }

    const sail = existsSync(resolve(root, 'vendor/bin/sail')) ? resolve(root, 'vendor/bin/sail') : null;

    if (!sail) {
        return;
    }

    const result = spawnSync(sail, ['restart', 'vite', 'laravel.test'], {
        cwd: root,
        stdio: 'inherit',
        env: process.env,
    });

    if (result.status !== 0) {
        console.error('Sail restart skipped or failed — run: npm run sail:up');
    }
}

if (!existsSync(envPath)) {
    console.error('Missing .env — copy .env.example first.');
    process.exit(1);
}

let env = readFileSync(envPath, 'utf8');
const appPort = readAppPort(env);

if (toLocalhost) {
    env = applyLaptopDevEnv(env);
    if (!dryRun) {
        writeFileSync(envPath, env);
    }
    console.log(`Sail (laptop): http://localhost:${appPort}`);
    restartSailServices();
    process.exit(0);
}

const lanHost = process.env.VITE_DEV_HOST?.trim() || readEnvValue(env, 'VITE_DEV_HOST') || detectLanIpv4();

if (!lanHost) {
    console.error('No LAN IPv4 found. Set VITE_DEV_HOST=192.168.x.x and re-run.');
    process.exit(1);
}

env = applyLanDevEnv(env, lanHost);
if (!dryRun) {
    writeFileSync(envPath, env);
}

console.log(`Sail (LAN): phone → http://${lanHost}:${appPort} · PC → http://localhost:${appPort}`);
restartSailServices();
