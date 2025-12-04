<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    modelValue?: number;
    rating?: number;
    readonly?: boolean;
    size?: 'sm' | 'md' | 'lg';
    showValue?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: 0,
    rating: 0,
    readonly: false,
    size: 'md',
    showValue: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

const currentRating = computed(() => props.modelValue || props.rating);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-4 w-4';
        case 'lg':
            return 'h-8 w-8';
        default:
            return 'h-5 w-5';
    }
});

const textSizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'text-sm';
        case 'lg':
            return 'text-xl';
        default:
            return 'text-base';
    }
});

function selectRating(value: number) {
    if (!props.readonly) {
        emit('update:modelValue', value);
    }
}
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            v-for="i in 5"
            :key="i"
            type="button"
            :disabled="readonly"
            :class="[
                'focus:outline-none transition-colors',
                readonly ? 'cursor-default' : 'cursor-pointer hover:scale-110',
            ]"
            @click="selectRating(i)"
        >
            <Star
                :class="[
                    sizeClasses,
                    i <= currentRating
                        ? 'fill-amber-400 text-amber-400'
                        : 'fill-muted text-muted',
                    !readonly && 'hover:fill-amber-300 hover:text-amber-300',
                ]"
            />
        </button>
        <span
            v-if="showValue && currentRating > 0"
            :class="['ml-1 font-semibold text-amber-600 dark:text-amber-500', textSizeClass]"
        >
            {{ currentRating.toFixed(1) }}
        </span>
    </div>
</template>
