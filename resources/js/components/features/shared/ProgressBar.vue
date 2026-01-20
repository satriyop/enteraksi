<script setup lang="ts">
// =============================================================================
// ProgressBar Component
// A flexible progress bar with multiple variants and color options
// =============================================================================

import { computed } from 'vue';
import { cn } from '@/lib/utils';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Progress value (0-100) */
    value: number;
    /** Maximum value (default: 100) */
    max?: number;
    /** Size variant */
    size?: 'sm' | 'md' | 'lg';
    /** Color variant */
    color?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    /** Whether to show percentage label */
    showLabel?: boolean;
    /** Label position */
    labelPosition?: 'inside' | 'outside' | 'above';
    /** Whether to animate the progress bar */
    animated?: boolean;
    /** Whether progress is indeterminate */
    indeterminate?: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    max: 100,
    size: 'md',
    color: 'default',
    showLabel: false,
    labelPosition: 'outside',
    animated: false,
    indeterminate: false,
});

// =============================================================================
// Computed Properties
// =============================================================================

const percentage = computed(() => {
    if (props.indeterminate) return 0;
    const clamped = Math.min(Math.max(props.value, 0), props.max);
    return Math.round((clamped / props.max) * 100);
});

const heightClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-1.5';
        case 'lg':
            return 'h-4';
        case 'md':
        default:
            return 'h-2.5';
    }
});

const barColorClasses = computed(() => {
    switch (props.color) {
        case 'success':
            return 'bg-green-500';
        case 'warning':
            return 'bg-yellow-500';
        case 'danger':
            return 'bg-red-500';
        case 'info':
            return 'bg-blue-500';
        case 'default':
        default:
            return 'bg-primary';
    }
});

const trackClasses = computed(() =>
    cn(
        'w-full overflow-hidden rounded-full bg-muted',
        heightClasses.value,
    )
);

const barClasses = computed(() =>
    cn(
        'h-full rounded-full transition-all duration-300 ease-in-out',
        barColorClasses.value,
        props.animated && 'animate-pulse',
        props.indeterminate && 'animate-indeterminate',
    )
);

const labelClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'text-xs';
        case 'lg':
            return 'text-sm';
        case 'md':
        default:
            return 'text-xs';
    }
});
</script>

<template>
    <div class="w-full">
        <!-- Label above -->
        <div v-if="showLabel && labelPosition === 'above'" class="flex justify-between mb-1">
            <span :class="['text-muted-foreground', labelClasses]">
                <slot name="label">Progres</slot>
            </span>
            <span :class="['font-medium', labelClasses]">{{ percentage }}%</span>
        </div>

        <!-- Progress track -->
        <div :class="trackClasses">
            <div
                v-if="!indeterminate"
                :class="barClasses"
                :style="{ width: `${percentage}%` }"
                role="progressbar"
                :aria-valuenow="value"
                :aria-valuemin="0"
                :aria-valuemax="max"
            >
                <!-- Label inside bar (only for lg size) -->
                <span
                    v-if="showLabel && labelPosition === 'inside' && size === 'lg' && percentage > 10"
                    class="flex h-full items-center justify-center text-xs font-medium text-white"
                >
                    {{ percentage }}%
                </span>
            </div>

            <!-- Indeterminate animation -->
            <div
                v-else
                :class="[barClasses, 'w-1/3']"
                style="animation: indeterminate 1.5s ease-in-out infinite"
            />
        </div>

        <!-- Label outside -->
        <div v-if="showLabel && labelPosition === 'outside'" class="mt-1 text-right">
            <span :class="['font-medium text-muted-foreground', labelClasses]">
                {{ percentage }}%
            </span>
        </div>
    </div>
</template>

<style scoped>
@keyframes indeterminate {
    0% {
        transform: translateX(-100%);
    }
    50% {
        transform: translateX(200%);
    }
    100% {
        transform: translateX(-100%);
    }
}

.animate-indeterminate {
    animation: indeterminate 1.5s ease-in-out infinite;
}
</style>
