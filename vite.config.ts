import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { visualizer } from 'rollup-plugin-visualizer';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // Bundle analyzer - generates stats.html after build
        visualizer({
            filename: 'stats.html',
            gzipSize: true,
            brotliSize: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    // Core Vue ecosystem
                    'vendor-vue': ['vue', '@inertiajs/vue3'],
                    // UI component libraries
                    'vendor-ui': [
                        'reka-ui',
                        'class-variance-authority',
                        'clsx',
                        'tailwind-merge',
                    ],
                    // Rich text editor (heavy)
                    'vendor-editor': [
                        '@tiptap/core',
                        '@tiptap/starter-kit',
                        '@tiptap/vue-3',
                        '@tiptap/extension-link',
                        '@tiptap/extension-image',
                        '@tiptap/extension-placeholder',
                        '@tiptap/extension-text-align',
                        '@tiptap/extension-underline',
                        '@tiptap/extension-code-block-lowlight',
                    ],
                    // Utilities
                    'vendor-utils': ['@vueuse/core', 'lucide-vue-next'],
                },
            },
        },
    },
});
