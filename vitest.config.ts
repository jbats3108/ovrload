import vue from '@vitejs/plugin-vue';
import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'happy-dom',
        setupFiles: ['resources/js/test/setup.ts'],
        include: ['resources/js/**/*.test.ts', 'vite/**/*.test.ts', 'scripts/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'lcov'],
            include: ['resources/js/**/*.ts', 'vite/**/*.ts'],
            exclude: [
                'resources/js/**/*.test.ts',
                'resources/js/**/*.d.ts',
                'resources/js/app.ts',
                'resources/js/ziggy.js',
                'resources/js/pages/**',
                'resources/js/components/ui/**',
                'vite/**/*.test.ts',
            ],
        },
    },
});
