/**
 * Start Sail for laptop-first local dev (localhost Vite HMR, no LAN IP churn).
 *
 * Usage (host):
 *   npm run sail:up              # PC at http://localhost:8000
 *   npm run sail:up -- --lan     # also inject fresh LAN IP for phone
 *   npm run sail:up -- --build
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { detectLanIpv4 } from '../vite/detectLanHost.ts';
import { recreateSailDevServices, runSail, waitForHttp } from './sail-cli.ts';
import { applyLanDevEnv, applyLaptopDevEnv, readAppPort, resolveLanHost } from './sail-env.ts';

const root = resolve(import.meta.dirname, '..');
const envPath = resolve(root, '.env');
const rawArgs = process.argv.includes('--') ? process.argv.slice(process.argv.indexOf('--') + 1) : process.argv.slice(2).filter((a) => a !== '--');
const useLan = rawArgs.includes('--lan');
const sailArgs = rawArgs.filter((a) => a !== '--lan');

if (!existsSync(envPath)) {
    console.error('Missing .env — copy .env.example first.');
    process.exit(1);
}

let env = readFileSync(envPath, 'utf8');
const appPort = readAppPort(env);
let lanHost: string | null = null;

if (useLan) {
    lanHost = resolveLanHost(process.env.VITE_DEV_HOST, detectLanIpv4());

    if (!lanHost) {
        console.error('No LAN IPv4 found. Set VITE_DEV_HOST=192.168.x.x or run without --lan.');
        process.exit(1);
    }

    env = applyLanDevEnv(env, lanHost);
    writeFileSync(envPath, env);
    console.log(`Sail (LAN): phone → http://${lanHost}:${appPort} · PC → http://localhost:${appPort}`);
} else {
    env = applyLaptopDevEnv(env);
    writeFileSync(envPath, env);
    console.log(`Sail (laptop): http://localhost:${appPort}`);
}

const status = runSail(['up', '-d', '--wait', ...sailArgs]);

if (status !== 0) {
    process.exit(status);
}

// Force-recreate Vite so public/hot matches the mode we just wrote to .env.
if (recreateSailDevServices(['vite']) !== 0) {
    process.exit(1);
}

const appUrl = `http://localhost:${appPort}`;

if (waitForHttp(appUrl)) {
    if (lanHost) {
        const viteOk = waitForHttp(`http://${lanHost}:5173`, 20, 500);
        console.log(
            viteOk ? `Ready: phone http://${lanHost}:${appPort} · PC ${appUrl}` : `Ready: ${appUrl} (LAN Vite probe failed — check :5173 firewall)`,
        );
    } else {
        console.log(`Ready: ${appUrl}`);
    }
} else {
    console.warn(`Stack is up but ${appUrl} did not respond yet — check: ./vendor/bin/sail ps`);
}

process.exit(0);
