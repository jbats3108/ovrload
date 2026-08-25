import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { readdirSync, unlinkSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { defineConfig, loadEnv, type Plugin } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';
import vueDevtools from 'vite-plugin-vue-devtools';
import { detectLanIpv4 } from './vite/detectLanHost';

/**
 * @inertiajs/vite SSR dev mode injects CSS via resolvedUrls.local (localhost), ignoring
 * server.origin. Rewrite local to the LAN origin so phones can load SSR stylesheets.
 */
function lanSsrDevOrigin(publicHost: string): Plugin {
    return {
        name: 'lan-ssr-dev-origin',
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                const origin = server.config.server.origin?.replace(/\/$/, '');

                if (!origin || !server.resolvedUrls?.local?.length) {
                    return;
                }

                const publicUrl = new URL(server.config.base, `${origin}/`).href;
                server.resolvedUrls.local[0] = publicUrl;
                if (server.resolvedUrls.network?.length) {
                    server.resolvedUrls.network[0] = publicUrl;
                }

                server.config.logger.info(`LAN dev: phone → http://${publicHost}:8000 · Vite → http://${publicHost}:5173`);
            });
        },
    };
}

/**
 * SW must live at /sw.js (not /build/sw.js) so its default scope is `/` and Chrome
 * can install the PWA. Hashed workbox helpers also land in public/ — clear stale ones.
 */
function cleanPublicPwaArtifacts(): Plugin {
    return {
        name: 'clean-public-pwa-artifacts',
        apply: 'build',
        buildStart() {
            const publicDir = resolve(import.meta.dirname, 'public');

            for (const file of readdirSync(publicDir)) {
                if (file === 'sw.js' || /^workbox-.+\.js$/.test(file)) {
                    unlinkSync(join(publicDir, file));
                }
            }
        },
    };
}

export default defineConfig(({ mode, isSsrBuild }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const sail = process.env.LARAVEL_SAIL === '1' || env.LARAVEL_SAIL === '1';
    const ddevUrl = env.DDEV_PRIMARY_URL || null;
    const lanDev = env.LAN_DEV === '1' || env.LAN_DEV === 'true';
    // Phone / LAN (opt-in): `npm run sail:up -- --lan` or `npm run sail:lan` sets VITE_DEV_HOST.
    // Laptop default: no VITE_DEV_HOST → localhost HMR inside Sail.
    const lanHost = process.env.VITE_DEV_HOST || env.VITE_DEV_HOST || (lanDev ? detectLanIpv4() : null);

    // DDEV: DDEV_PRIMARY_URL is set automatically.
    const publicHost = lanHost || (ddevUrl ? new URL(ddevUrl).hostname : null);
    const shareOnLan = Boolean(publicHost);

    const server = shareOnLan
        ? {
              host: '0.0.0.0',
              port: 5173,
              strictPort: true,
              // Phone + Docker service DNS may hit Vite with Host != origin hostname.
              allowedHosts: true,
              origin: `http://${publicHost}:5173`,
              hmr: {
                  host: publicHost,
              },
              cors: {
                  origin: true,
              },
          }
        : sail
          ? {
                host: '0.0.0.0',
                port: 5173,
                strictPort: true,
                allowedHosts: true,
                hmr: {
                    host: 'localhost',
                },
                cors: {
                    origin: true,
                },
            }
          : undefined;

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                refresh: true,
            }),
            inertia({
                ssr: {
                    cluster: true,
                    host: '127.0.0.1',
                },
            }),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            vueDevtools(),
            // Wipe stale root SW before client emit. Skip on SSR so we don't delete it.
            ...(!isSsrBuild ? [cleanPublicPwaArtifacts()] : []),
            VitePWA({
                // Keep the plugin on SSR so `virtual:pwa-register` resolves; skip SW emit.
                disable: isSsrBuild,
                registerType: 'autoUpdate',
                injectRegister: false,
                manifest: false,
                // Emit SW at public/sw.js so scope defaults to `/` (installable on Android).
                outDir: 'public',
                scope: '/',
                base: '/',
                buildBase: '/',
                workbox: {
                    globDirectory: resolve(import.meta.dirname, 'public/build'),
                    globPatterns: ['**/*.{js,css,ico,png,svg,woff2,webmanifest}'],
                    modifyURLPrefix: {
                        '': '/build/',
                    },
                    navigateFallback: null,
                    cleanupOutdatedCaches: true,
                    additionalManifestEntries: [
                        { url: '/manifest.webmanifest', revision: '2' },
                        { url: '/pwa-192x192.png', revision: '2' },
                        { url: '/pwa-512x512.png', revision: '2' },
                        { url: '/pwa-512x512-maskable.png', revision: '2' },
                    ],
                },
                devOptions: {
                    enabled: false,
                },
            }),
            ...(shareOnLan ? [lanSsrDevOrigin(publicHost!)] : []),
        ],
        server,
    };
});
