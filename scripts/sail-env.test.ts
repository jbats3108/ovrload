import { describe, expect, it } from 'vitest';
import { applyLanDevEnv, applyLaptopDevEnv, commentEnv, readAppPort, readEnvValue, resolveLanHost, upsertEnv } from './sail-env';

describe('sail-env', () => {
    it('reads and upserts env values', () => {
        const env = 'FOO=bar\nAPP_PORT=9000\n';

        expect(readEnvValue(env, 'FOO')).toBe('bar');
        expect(readAppPort(env)).toBe('9000');
        expect(upsertEnv(env, 'FOO', 'baz')).toBe('FOO=baz\nAPP_PORT=9000\n');
    });

    it('comments out keys for laptop mode', () => {
        const env = 'VITE_DEV_HOST=192.168.0.104\nAPP_URL=http://192.168.0.131:8000\nAPP_PORT=8000\n';
        const next = applyLaptopDevEnv(env);

        expect(next).toContain('# VITE_DEV_HOST=');
        expect(next).not.toMatch(/^VITE_DEV_HOST=/m);
        expect(next).toContain('APP_URL=http://localhost:8000');
    });

    it('injects LAN host and keeps APP_URL on localhost', () => {
        const env = 'VITE_DEV_HOST=192.168.0.1\nAPP_URL=http://192.168.0.1:8000\nAPP_PORT=8000\n';
        const next = applyLanDevEnv(env, '192.168.0.50');

        expect(next).toContain('VITE_DEV_HOST=192.168.0.50');
        expect(next).toContain('APP_URL=http://localhost:8000');
        expect(next).not.toContain('APP_URL=http://192.168.0.1:8000');
    });

    it('prefers process override over detection and ignores stale .env values', () => {
        expect(resolveLanHost('10.0.0.9', '192.168.0.104')).toBe('10.0.0.9');
        expect(resolveLanHost(undefined, '192.168.0.104')).toBe('192.168.0.104');
        expect(resolveLanHost('  ', '192.168.0.104')).toBe('192.168.0.104');
        expect(resolveLanHost(undefined, null)).toBeNull();
    });

    it('comments missing keys harmlessly', () => {
        expect(commentEnv('APP_URL=http://localhost:8000\n', 'VITE_DEV_HOST')).toBe('APP_URL=http://localhost:8000\n');
    });
});
