import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        globals: true,
        environment: 'happy-dom',
        include: ['resources/js/**/*.{test,spec}.{js,ts}'],
        exclude: ['node_modules', 'vendor'],
        setupFiles: ['./resources/js/tests/setup.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html', 'lcov'],
            include: [
                'resources/js/components/**/*.vue',
                'resources/js/composables/**/*.ts',
                'resources/js/lib/**/*.ts',
                'resources/js/stores/**/*.ts',
            ],
            exclude: [
                'resources/js/components/ui/**', // shadcn-vue components
                '**/*.d.ts',
                '**/*.test.ts',
                '**/*.spec.ts',
                '**/index.ts', // barrel files
            ],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, './resources/js'),
        },
    },
});
