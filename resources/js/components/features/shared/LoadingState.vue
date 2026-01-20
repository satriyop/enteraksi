<script setup lang="ts">
// =============================================================================
// LoadingState Component
// A flexible loading indicator with skeleton and spinner variants
// =============================================================================

import { computed } from 'vue';
import { Loader2 } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Loading text message */
    text?: string;
    /** Size variant */
    size?: 'sm' | 'md' | 'lg';
    /** Display inline instead of centered block */
    inline?: boolean;
    /** Variant: spinner or skeleton */
    variant?: 'spinner' | 'skeleton';
    /** Number of skeleton lines (only for skeleton variant) */
    lines?: number;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    text: 'Memuat...',
    size: 'md',
    inline: false,
    variant: 'spinner',
    lines: 3,
});

// =============================================================================
// Computed Properties
// =============================================================================

const spinnerSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-4 w-4';
        case 'lg':
            return 'h-8 w-8';
        case 'md':
        default:
            return 'h-6 w-6';
    }
});

const textSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'text-xs';
        case 'lg':
            return 'text-base';
        case 'md':
        default:
            return 'text-sm';
    }
});

const skeletonWidths = ['w-full', 'w-5/6', 'w-4/5', 'w-3/4', 'w-2/3'];
</script>

<template>
    <!-- Spinner Variant -->
    <div
        v-if="variant === 'spinner'"
        :class="[
            'flex items-center gap-2 text-muted-foreground',
            inline ? 'inline-flex' : 'flex-col justify-center py-8',
        ]"
    >
        <Loader2 :class="['animate-spin', spinnerSizeClasses]" />
        <span v-if="text" :class="textSizeClasses">{{ text }}</span>
    </div>

    <!-- Skeleton Variant -->
    <div v-else class="animate-pulse space-y-3">
        <div
            v-for="i in lines"
            :key="i"
            :class="[
                'h-4 rounded bg-muted',
                skeletonWidths[(i - 1) % skeletonWidths.length],
            ]"
        />
    </div>
</template>
