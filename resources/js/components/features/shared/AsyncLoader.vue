<script setup lang="ts">
// =============================================================================
// AsyncLoader Component
// Wrapper for async components with loading and error states
// =============================================================================

import { ref, onErrorCaptured } from 'vue';
import { AlertCircle, RefreshCw } from 'lucide-vue-next';
import LoadingState from './LoadingState.vue';
import { Button } from '@/components/ui/button';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Loading text to display */
    loadingText?: string;
    /** Error title when component fails to load */
    errorTitle?: string;
    /** Error description */
    errorDescription?: string;
    /** Size of loading indicator */
    size?: 'sm' | 'md' | 'lg';
    /** Variant of loading indicator */
    loadingVariant?: 'spinner' | 'skeleton';
    /** Number of skeleton lines */
    skeletonLines?: number;
}

// =============================================================================
// Component Setup
// =============================================================================

withDefaults(defineProps<Props>(), {
    loadingText: 'Memuat...',
    errorTitle: 'Gagal memuat',
    errorDescription: 'Terjadi kesalahan saat memuat komponen',
    size: 'md',
    loadingVariant: 'spinner',
    skeletonLines: 3,
});

// =============================================================================
// State
// =============================================================================

const error = ref<Error | null>(null);

// =============================================================================
// Error Handling
// =============================================================================

onErrorCaptured((err) => {
    error.value = err;
    return false; // Don't propagate
});

function retry() {
    error.value = null;
}
</script>

<template>
    <!-- Error State -->
    <div v-if="error" class="flex flex-col items-center justify-center py-8 text-center">
        <div
            class="flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10"
        >
            <AlertCircle class="h-6 w-6 text-destructive" />
        </div>
        <h3 class="mt-4 font-semibold text-foreground">{{ errorTitle }}</h3>
        <p class="mt-1 text-sm text-muted-foreground">{{ errorDescription }}</p>
        <Button variant="outline" size="sm" class="mt-4" @click="retry">
            <RefreshCw class="mr-2 h-4 w-4" />
            Coba Lagi
        </Button>
    </div>

    <!-- Suspense with Loading -->
    <Suspense v-else>
        <template #default>
            <slot />
        </template>
        <template #fallback>
            <LoadingState
                :text="loadingText"
                :size="size"
                :variant="loadingVariant"
                :lines="skeletonLines"
            />
        </template>
    </Suspense>
</template>
