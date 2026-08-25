import { describe, expect, it } from 'vitest';
import { applyLanDevEnv, applyLaptopDevEnv, commentEnv, readAppPort, readEnvValue, upsertEnv } from './sail-env';

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

    it('injects LAN host without touching APP_URL', () => {
        const env = '# VITE_DEV_HOST=\nAPP_URL=http://localhost:8000\n';
        const next = applyLanDevEnv(env, '192.168.0.50');

        expect(next).toContain('VITE_DEV_HOST=192.168.0.50');
        expect(next).toContain('APP_URL=http://localhost:8000');
    });

    it('comments missing keys harmlessly', () => {
        expect(commentEnv('APP_URL=http://localhost:8000\n', 'VITE_DEV_HOST')).toBe('APP_URL=http://localhost:8000\n');
    });
});
