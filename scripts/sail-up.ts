/**
 * Start Sail for laptop-first local dev (localhost Vite HMR, no LAN IP churn).
 *
 * Usage (host):
 *   npm run sail:up              # PC at http://localhost:8000
 *   npm run sail:up -- --lan     # also inject LAN IP for phone (see npm run sail:lan)
 *   npm run sail:up -- --build
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { detectLanIpv4 } from '../vite/detectLanHost.ts';
import { applyLanDevEnv, applyLaptopDevEnv, readAppPort, readEnvValue } from './sail-env.ts';

const root = resolve(import.meta.dirname, '..');
const envPath = resolve(root, '.env');
const rawArgs = process.argv.includes('--') ? process.argv.slice(process.argv.indexOf('--') + 1) : process.argv.slice(2).filter((a) => a !== '--');
const useLan = rawArgs.includes('--lan');
const sailArgs = rawArgs.filter((a) => a !== '--lan');

function runSail(args: string[]): number {
    const sail = resolve(root, 'vendor/bin/sail');

    if (!existsSync(sail)) {
        console.error('Missing vendor/bin/sail — run composer install.');
        process.exit(1);
    }

    const result = spawnSync(sail, args, { cwd: root, stdio: 'inherit', env: process.env });

    return result.status ?? 1;
}

function waitForHttp(url: string, attempts = 30, delayMs = 1000): boolean {
    for (let i = 0; i < attempts; i++) {
        const probe = spawnSync('curl', ['-s', '-o', '/dev/null', '-w', '%{http_code}', url], {
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

if (!existsSync(envPath)) {
    console.error('Missing .env — copy .env.example first.');
    process.exit(1);
}

let env = readFileSync(envPath, 'utf8');
const appPort = readAppPort(env);

if (useLan) {
    const lanHost = process.env.VITE_DEV_HOST?.trim() || readEnvValue(env, 'VITE_DEV_HOST') || detectLanIpv4();

    if (!lanHost) {
        console.error('No LAN IPv4 found. Set VITE_DEV_HOST=192.168.x.x or run without --lan.');
        process.exit(1);
    }

    env = applyLanDevEnv(env, lanHost);
    console.log(`Sail (LAN): phone → http://${lanHost}:${appPort} · PC → http://localhost:${appPort}`);
} else {
    env = applyLaptopDevEnv(env);
    console.log(`Sail (laptop): http://localhost:${appPort}`);
}

writeFileSync(envPath, env);

const status = runSail(['up', '-d', '--wait', ...sailArgs]);

if (status !== 0) {
    process.exit(status);
}

// Vite reads VITE_DEV_HOST at start — ensure public/hot matches laptop vs LAN mode.
runSail(['restart', 'vite']);

const appUrl = `http://localhost:${appPort}`;

if (waitForHttp(appUrl)) {
    console.log(`Ready: ${appUrl}`);
} else {
    console.warn(`Stack is up but ${appUrl} did not respond yet — check: ./vendor/bin/sail ps`);
}

process.exit(0);
