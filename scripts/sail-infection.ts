/**
 * Run Infection (mutation testing) inside Sail (PCOV; Xdebug off for the run).
 *
 * Pilot scope: Support + Policies (see infection.json5).
 *
 * Usage (host, Sail must be up):
 *   npm run sail:infection
 *   npm run sail:infection -- --show-mutations
 *   composer test:infection
 */
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { runSail, sailBin } from './sail-cli.ts';

const root = resolve(import.meta.dirname, '..');
const rawArgs = process.argv.includes('--') ? process.argv.slice(process.argv.indexOf('--') + 1) : process.argv.slice(2).filter((a) => a !== '--');

const ps = spawnSync(sailBin(), ['ps', '-q', 'laravel.test'], {
    cwd: root,
    encoding: 'utf8',
    env: process.env,
});

if ((ps.status ?? 1) !== 0 || !ps.stdout?.trim()) {
    console.error('Sail is not running (laravel.test). Start it with: npm run sail:up');
    process.exit(1);
}

const status = runSail([
    'exec',
    '-e',
    'XDEBUG_MODE=off',
    'laravel.test',
    'php',
    '-d',
    'xdebug.mode=off',
    'vendor/bin/infection',
    '--threads=max',
    ...rawArgs,
]);

if (status === 0) {
    console.log('Infection HTML: coverage/infection.html');
}

process.exit(status);
