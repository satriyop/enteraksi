<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Loader2 } from 'lucide-vue-next';

interface Props {
    videoId: string;
    initialPosition?: number;
}

const props = withDefaults(defineProps<Props>(), {
    initialPosition: 0,
});

const emit = defineEmits<{
    ready: [];
    play: [];
    pause: [];
    ended: [];
    timeupdate: [currentTime: number, duration: number];
}>();

// State
const playerRef = ref<HTMLDivElement | null>(null);
const isLoading = ref(true);
const hasError = ref(false);

// YouTube Player instance
let player: YT.Player | null = null;
let progressInterval: ReturnType<typeof setInterval> | null = null;

// Load YouTube IFrame API
const loadYouTubeAPI = (): Promise<void> => {
    return new Promise((resolve, reject) => {
        // Check if already loaded
        if (window.YT && window.YT.Player) {
            resolve();
            return;
        }

        // Check if script is already loading
        if (document.querySelector('script[src*="youtube.com/iframe_api"]')) {
            // Wait for it to load
            const checkReady = setInterval(() => {
                if (window.YT && window.YT.Player) {
                    clearInterval(checkReady);
                    resolve();
                }
            }, 100);
            return;
        }

        // Create callback
        const callbackName = 'onYouTubeIframeAPIReady';
        const existingCallback = (window as Record<string, unknown>)[callbackName] as (() => void) | undefined;

        (window as Record<string, unknown>)[callbackName] = () => {
            if (existingCallback) {
                existingCallback();
            }
            resolve();
        };

        // Load script
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        script.onerror = () => reject(new Error('Failed to load YouTube API'));
        document.head.appendChild(script);
    });
};

// Initialize player
const initPlayer = async () => {
    if (!playerRef.value) return;

    try {
        await loadYouTubeAPI();

        // Create unique ID
        const playerId = `yt-player-${Date.now()}`;
        playerRef.value.id = playerId;

        player = new window.YT.Player(playerId, {
            videoId: props.videoId,
            playerVars: {
                autoplay: 0,
                modestbranding: 1,
                rel: 0,
                start: props.initialPosition,
            },
            events: {
                onReady: handleReady,
                onStateChange: handleStateChange,
                onError: handleError,
            },
        });
    } catch (error) {
        console.error('Failed to initialize YouTube player:', error);
        hasError.value = true;
        isLoading.value = false;
    }
};

// Event handlers
const handleReady = () => {
    isLoading.value = false;
    emit('ready');

    // Seek to initial position if provided
    if (props.initialPosition > 0 && player) {
        player.seekTo(props.initialPosition, true);
    }
};

const handleStateChange = (event: YT.OnStateChangeEvent) => {
    switch (event.data) {
        case window.YT.PlayerState.PLAYING:
            emit('play');
            startProgressTracking();
            break;
        case window.YT.PlayerState.PAUSED:
            emit('pause');
            stopProgressTracking();
            // Emit one final update on pause
            emitProgress();
            break;
        case window.YT.PlayerState.ENDED:
            emit('ended');
            stopProgressTracking();
            emitProgress();
            break;
    }
};

const handleError = () => {
    hasError.value = true;
    isLoading.value = false;
};

// Progress tracking
const startProgressTracking = () => {
    if (progressInterval) return;

    progressInterval = setInterval(() => {
        emitProgress();
    }, 1000); // Update every second
};

const stopProgressTracking = () => {
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
};

const emitProgress = () => {
    if (!player) return;

    try {
        const currentTime = Math.floor(player.getCurrentTime());
        const duration = Math.floor(player.getDuration());

        if (duration > 0) {
            emit('timeupdate', currentTime, duration);
        }
    } catch {
        // Player might not be ready
    }
};

// Public methods
const getCurrentTime = (): number => {
    return player ? Math.floor(player.getCurrentTime()) : 0;
};

const getDuration = (): number => {
    return player ? Math.floor(player.getDuration()) : 0;
};

const seekTo = (seconds: number) => {
    player?.seekTo(seconds, true);
};

// Expose methods to parent
defineExpose({
    getCurrentTime,
    getDuration,
    seekTo,
});

// Lifecycle
onMounted(() => {
    initPlayer();
});

onUnmounted(() => {
    stopProgressTracking();
    if (player) {
        player.destroy();
        player = null;
    }
});

// Watch for video ID changes
watch(() => props.videoId, (newId) => {
    if (player && newId) {
        player.loadVideoById(newId);
    }
});
</script>

<template>
    <div class="youtube-player relative w-full aspect-video bg-black rounded-lg overflow-hidden">
        <!-- Loading state -->
        <div
            v-if="isLoading"
            class="absolute inset-0 flex items-center justify-center bg-black"
        >
            <Loader2 class="h-8 w-8 animate-spin text-white" />
        </div>

        <!-- Error state -->
        <div
            v-if="hasError"
            class="absolute inset-0 flex items-center justify-center bg-black"
        >
            <div class="text-center text-white">
                <p class="mb-2">Gagal memuat video</p>
                <button
                    type="button"
                    class="px-4 py-2 bg-white/20 rounded hover:bg-white/30 transition-colors"
                    @click="initPlayer"
                >
                    Coba Lagi
                </button>
            </div>
        </div>

        <!-- Player container -->
        <div ref="playerRef" class="w-full h-full" />
    </div>
</template>

<style scoped>
.youtube-player :deep(iframe) {
    width: 100%;
    height: 100%;
}
</style>
