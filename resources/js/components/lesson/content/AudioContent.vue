<script setup lang="ts">
// =============================================================================
// AudioContent Component
// Displays audio content with custom player UI and progress tracking
// =============================================================================

import { ref, onMounted } from 'vue';
import type { Media } from '@/types';
import { Play, Pause } from 'lucide-vue-next';

interface Props {
    /** Audio media object */
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
    /** Emitted when audio is paused */
    pause: [];
    /** Emitted when audio ends */
    ended: [];
}>();

const audioRef = ref<HTMLAudioElement | null>(null);
const isPlaying = ref(false);

onMounted(() => {
    if (audioRef.value && props.initialPosition > 0) {
        audioRef.value.currentTime = props.initialPosition;
    }
});

const togglePlay = () => {
    if (audioRef.value) {
        if (isPlaying.value) {
            audioRef.value.pause();
        } else {
            audioRef.value.play();
        }
        isPlaying.value = !isPlaying.value;
    }
};

const handleTimeUpdate = () => {
    if (audioRef.value) {
        const currentTime = audioRef.value.currentTime;
        const duration = audioRef.value.duration;
        if (duration > 0 && !isNaN(duration)) {
            emit('timeupdate', currentTime, duration);
        }
    }
};

const handlePause = () => {
    isPlaying.value = false;
    emit('pause');
};

const handleEnded = () => {
    isPlaying.value = false;
    emit('ended');
};

const handlePlay = () => {
    isPlaying.value = true;
};
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 p-8">
            <div class="flex flex-col items-center gap-6">
                <div class="relative">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-primary shadow-lg">
                        <button
                            type="button"
                            @click="togglePlay"
                            class="flex h-full w-full items-center justify-center rounded-full text-primary-foreground hover:scale-105 transition-transform"
                        >
                            <Pause v-if="isPlaying" class="h-10 w-10" />
                            <Play v-else class="h-10 w-10 ml-1" />
                        </button>
                    </div>
                    <div
                        v-if="isPlaying"
                        class="absolute inset-0 rounded-full border-4 border-primary/30 animate-ping"
                    />
                </div>
                <div class="text-center">
                    <p class="font-medium">{{ media.file_name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ media.duration_formatted || media.human_readable_size }}
                    </p>
                </div>
            </div>
        </div>

        <audio
            ref="audioRef"
            :src="media.url"
            class="w-full"
            controls
            @timeupdate="handleTimeUpdate"
            @pause="handlePause"
            @play="handlePlay"
            @ended="handleEnded"
        />
    </div>
</template>
