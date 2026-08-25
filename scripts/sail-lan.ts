/**
 * Switch an already-running Sail stack to phone-reachable LAN Vite HMR (or back to laptop-only).
 *
 * Always re-detects the LAN IP (unless VITE_DEV_HOST is set in the shell) so stale
 * Wi‑Fi addresses do not break phone Vite/HMR.
 *
 * Usage (host, not inside Sail):
 *   npm run sail:lan
 *   npm run sail:localhost
 *   VITE_DEV_HOST=192.168.x.x npm run sail:lan
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { detectLanIpv4 } from '../vite/detectLanHost.ts';
import { recreateSailDevServices, waitForHttp } from './sail-cli.ts';
import { applyLanDevEnv, applyLaptopDevEnv, readAppPort, resolveLanHost } from './sail-env.ts';

const root = resolve(import.meta.dirname, '..');
const envPath = resolve(root, '.env');
const toLocalhost = process.argv.includes('--localhost');
const dryRun = process.argv.includes('--dry-run');

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
        console.log(`Sail (laptop): http://localhost:${appPort}`);
        if (recreateSailDevServices() !== 0) {
            process.exit(1);
        }
        if (waitForHttp(`http://localhost:${appPort}`)) {
            console.log(`Ready: http://localhost:${appPort}`);
        }
    } else {
        console.log(`[dry-run] would set laptop mode → http://localhost:${appPort}`);
    }
    process.exit(0);
}

const lanHost = resolveLanHost(process.env.VITE_DEV_HOST, detectLanIpv4());

if (!lanHost) {
    console.error('No LAN IPv4 found. Set VITE_DEV_HOST=192.168.x.x and re-run.');
    process.exit(1);
}

env = applyLanDevEnv(env, lanHost);
if (!dryRun) {
    writeFileSync(envPath, env);
    console.log(`Sail (LAN): phone → http://${lanHost}:${appPort} · PC → http://localhost:${appPort}`);
    if (recreateSailDevServices() !== 0) {
        process.exit(1);
    }

    const appOk = waitForHttp(`http://localhost:${appPort}`);
    const viteOk = waitForHttp(`http://${lanHost}:5173`, 20, 500);

    if (appOk && viteOk) {
        console.log(`Ready: phone http://${lanHost}:${appPort} · Vite http://${lanHost}:5173`);
    } else {
        console.warn('Stack recreated but probes failed — check firewall for :8000 and :5173, then ./vendor/bin/sail ps');
        if (!appOk) {
            console.warn(`  app not ready: http://localhost:${appPort}`);
        }
        if (!viteOk) {
            console.warn(`  Vite not reachable on LAN: http://${lanHost}:5173`);
        }
    }
} else {
    console.log(`[dry-run] would set VITE_DEV_HOST=${lanHost}`);
}
