/**
 * Run PHPUnit coverage inside Sail (PCOV; Xdebug off for the run).
 *
 * Usage (host, Sail must be up):
 *   npm run sail:coverage
 *   npm run sail:coverage -- --filter=SomeTest
 *   npm run sail:coverage -- --check
 *   composer test:coverage
 */
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { runSail, sailBin } from './sail-cli.ts';

const root = resolve(import.meta.dirname, '..');
const rawArgs = process.argv.includes('--') ? process.argv.slice(process.argv.indexOf('--') + 1) : process.argv.slice(2).filter((a) => a !== '--');
const checkOnly = rawArgs.includes('--check');
const passthrough = rawArgs.filter((a) => a !== '--check');

const ps = spawnSync(sailBin(), ['ps', '-q', 'laravel.test'], {
    cwd: root,
    encoding: 'utf8',
    env: process.env,
});

if ((ps.status ?? 1) !== 0 || !ps.stdout?.trim()) {
    console.error('Sail is not running (laravel.test). Start it with: npm run sail:up');
    process.exit(1);
}

const phpCmd = checkOnly
    ? ['artisan', 'test', '--coverage', '--min=50', ...passthrough]
    : [
          'vendor/bin/phpunit',
          '--coverage-html=coverage',
          '--coverage-text',
          '--coverage-clover=coverage/clover.xml',
          '--coverage-crap4j=coverage/crap4j.xml',
          '--colors=always',
          ...passthrough,
      ];

const status = runSail(['exec', '-e', 'XDEBUG_MODE=off', 'laravel.test', 'php', '-d', 'xdebug.mode=off', ...phpCmd]);

if (status === 0 && !checkOnly) {
    console.log('Coverage HTML: coverage/index.html');
}

process.exit(status);
