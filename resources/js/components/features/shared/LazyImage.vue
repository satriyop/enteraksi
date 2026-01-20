<script setup lang="ts">
// =============================================================================
// LazyImage Component
// Lazy loading image with placeholder and error handling
// =============================================================================

import { ref, onMounted, onUnmounted } from 'vue';
import { ImageOff } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Image source URL */
    src: string;
    /** Alternative text */
    alt: string;
    /** Placeholder image while loading */
    placeholder?: string;
    /** Image width */
    width?: number | string;
    /** Image height */
    height?: number | string;
    /** Additional CSS classes */
    class?: string;
    /** Object fit style */
    fit?: 'cover' | 'contain' | 'fill' | 'none' | 'scale-down';
    /** Root margin for intersection observer */
    rootMargin?: string;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    placeholder: '',
    fit: 'cover',
    rootMargin: '50px',
});

// =============================================================================
// State
// =============================================================================

const imageRef = ref<HTMLImageElement | null>(null);
const isLoaded = ref(false);
const hasError = ref(false);
const isInView = ref(false);

let observer: IntersectionObserver | null = null;

// =============================================================================
// Image Loading
// =============================================================================

function loadImage() {
    if (!props.src || hasError.value || isLoaded.value) return;

    const img = new Image();

    img.onload = () => {
        isLoaded.value = true;
    };

    img.onerror = () => {
        hasError.value = true;
    };

    img.src = props.src;
}

// =============================================================================
// Intersection Observer
// =============================================================================

onMounted(() => {
    if (!imageRef.value) return;

    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting) {
                isInView.value = true;
                loadImage();
                observer?.disconnect();
            }
        },
        { rootMargin: props.rootMargin }
    );

    observer.observe(imageRef.value);
});

onUnmounted(() => {
    observer?.disconnect();
});

// =============================================================================
// Computed Classes
// =============================================================================

const fitClasses: Record<string, string> = {
    cover: 'object-cover',
    contain: 'object-contain',
    fill: 'object-fill',
    none: 'object-none',
    'scale-down': 'object-scale-down',
};
</script>

<template>
    <div
        ref="imageRef"
        :class="[
            'relative overflow-hidden bg-muted',
            props.class,
        ]"
        :style="{
            width: typeof width === 'number' ? `${width}px` : width,
            height: typeof height === 'number' ? `${height}px` : height,
        }"
    >
        <!-- Error State -->
        <div
            v-if="hasError"
            class="flex h-full w-full items-center justify-center bg-muted"
        >
            <ImageOff class="h-8 w-8 text-muted-foreground/50" />
        </div>

        <!-- Image -->
        <img
            v-else
            :src="isLoaded ? src : placeholder"
            :alt="alt"
            :class="[
                'h-full w-full transition-opacity duration-300',
                fitClasses[fit],
                isLoaded ? 'opacity-100' : 'opacity-0',
            ]"
            loading="lazy"
            decoding="async"
        />

        <!-- Loading Skeleton -->
        <div
            v-if="!isLoaded && !hasError"
            class="absolute inset-0 animate-pulse bg-muted"
        />
    </div>
</template>
