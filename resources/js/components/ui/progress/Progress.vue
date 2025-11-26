<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    modelValue?: number;
    max?: number;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: 0,
    max: 100,
});

const percentage = computed(() => {
    return Math.min(Math.max((props.modelValue / props.max) * 100, 0), 100);
});
</script>

<template>
    <div
        role="progressbar"
        :aria-valuenow="modelValue"
        :aria-valuemin="0"
        :aria-valuemax="max"
        :class="cn('relative h-4 w-full overflow-hidden rounded-full bg-secondary', props.class)"
    >
        <div
            class="h-full bg-primary transition-all duration-300 ease-in-out"
            :style="{ width: `${percentage}%` }"
        />
    </div>
</template>
