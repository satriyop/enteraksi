<script setup lang="ts">
// =============================================================================
// VideoContent Component
// Displays video content with progress tracking
// =============================================================================

import { ref, onMounted } from 'vue';
import type { Media } from '@/types';

interface Props {
    /** Video media object */
    media: Media;
    /** Initial playback position in seconds */
    initialPosition?: number;
}

const props = withDefaults(defineProps<Props>(), {
    initialPosition: 0,
});

const emit = defineEmits<{
    /** Emitted on time update */
    timeupdate: [currentTime: number, duration: number];
    /** Emitted when video is paused */
    pause: [];
    /** Emitted when video ends */
    ended: [];
}>();

const videoRef = ref<HTMLVideoElement | null>(null);

onMounted(() => {
    if (videoRef.value && props.initialPosition > 0) {
        videoRef.value.currentTime = props.initialPosition;
    }
});

const handleTimeUpdate = () => {
    if (videoRef.value) {
        const currentTime = videoRef.value.currentTime;
        const duration = videoRef.value.duration;
        if (duration > 0 && !isNaN(duration)) {
            emit('timeupdate', currentTime, duration);
        }
    }
};

const handlePause = () => {
    emit('pause');
};

const handleEnded = () => {
    emit('ended');
};
</script>

<template>
    <video
        ref="videoRef"
        :src="media.url"
        class="w-full h-full"
        controls
        controlsList="nodownload"
        @timeupdate="handleTimeUpdate"
        @pause="handlePause"
        @ended="handleEnded"
    />
</template>
