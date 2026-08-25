import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';

const certPath = 'C:/laragon/etc/ssl/laragon.crt';
const keyPath = 'C:/laragon/etc/ssl/laragon.key';
const hasSsl = fs.existsSync(certPath) && fs.existsSync(keyPath);

export default defineConfig({
    server: {
        host: '0.0.0.0',
        cors: true,
        https: hasSsl
            ? {
                  key: fs.readFileSync(keyPath),
                  cert: fs.readFileSync(certPath),
              }
            : false,
        hmr: {
            host: '192.168.1.180',
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
