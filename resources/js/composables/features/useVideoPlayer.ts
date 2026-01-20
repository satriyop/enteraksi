// =============================================================================
// useVideoPlayer Composable
// Video player state and controls management
// =============================================================================

import { ref, computed, watch, onMounted, onUnmounted, type Ref } from 'vue';
import { STORAGE_KEYS, DEBOUNCE } from '@/lib/constants';
import { debounce, safeJsonParse } from '@/lib/utils';

// =============================================================================
// Types
// =============================================================================

interface UseVideoPlayerOptions {
    /** Ref to the video element */
    videoRef: Ref<HTMLVideoElement | null>;
    /** Unique ID for saving progress (e.g., lessonId) */
    progressKey?: string | number;
    /** Callback when progress updates */
    onProgress?: (percentage: number, position: number) => void;
    /** Callback when video completes */
    onComplete?: () => void;
    /** Completion threshold percentage (default: 90) */
    completionThreshold?: number;
}

interface SavedProgress {
    position: number;
    timestamp: number;
}

// =============================================================================
// Composable
// =============================================================================

export function useVideoPlayer(options: UseVideoPlayerOptions) {
    const {
        videoRef,
        progressKey,
        onProgress,
        onComplete,
        completionThreshold = 90,
    } = options;

    // =============================================================================
    // State
    // =============================================================================

    const isPlaying = ref(false);
    const currentTime = ref(0);
    const duration = ref(0);
    const buffered = ref(0);
    const volume = ref(1);
    const isMuted = ref(false);
    const playbackRate = ref(1);
    const isFullscreen = ref(false);
    const isLoading = ref(true);
    const error = ref<string | null>(null);
    const hasCompleted = ref(false);

    // Storage key for progress
    const storageKey = progressKey
        ? `${STORAGE_KEYS.videoProgress}-${progressKey}`
        : null;

    // =============================================================================
    // Computed
    // =============================================================================

    const progress = computed(() =>
        duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0
    );

    const bufferedProgress = computed(() =>
        duration.value > 0 ? (buffered.value / duration.value) * 100 : 0
    );

    const formattedCurrentTime = computed(() => formatTime(currentTime.value));
    const formattedDuration = computed(() => formatTime(duration.value));

    const canPlay = computed(() => !isLoading.value && !error.value);

    // =============================================================================
    // Helpers
    // =============================================================================

    /**
     * Format seconds to MM:SS or HH:MM:SS
     */
    function formatTime(seconds: number): string {
        if (isNaN(seconds) || seconds < 0) return '0:00';

        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hrs > 0) {
            return `${hrs}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // =============================================================================
    // Playback Controls
    // =============================================================================

    function play(): void {
        videoRef.value?.play().catch(() => {
            error.value = 'Gagal memutar video';
        });
    }

    function pause(): void {
        videoRef.value?.pause();
    }

    function togglePlay(): void {
        if (isPlaying.value) {
            pause();
        } else {
            play();
        }
    }

    function seek(time: number): void {
        if (videoRef.value) {
            videoRef.value.currentTime = Math.max(0, Math.min(time, duration.value));
        }
    }

    function seekRelative(delta: number): void {
        seek(currentTime.value + delta);
    }

    function seekToPercent(percent: number): void {
        seek((percent / 100) * duration.value);
    }

    function setVolume(value: number): void {
        if (videoRef.value) {
            volume.value = Math.max(0, Math.min(1, value));
            videoRef.value.volume = volume.value;
            isMuted.value = volume.value === 0;
        }
    }

    function toggleMute(): void {
        if (videoRef.value) {
            isMuted.value = !isMuted.value;
            videoRef.value.muted = isMuted.value;
        }
    }

    function setPlaybackRate(rate: number): void {
        if (videoRef.value) {
            playbackRate.value = rate;
            videoRef.value.playbackRate = rate;
        }
    }

    function toggleFullscreen(): void {
        if (!videoRef.value) return;

        const container = videoRef.value.parentElement;
        if (!container) return;

        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            container.requestFullscreen();
        }
    }

    // =============================================================================
    // Progress Persistence
    // =============================================================================

    const savePosition = debounce(() => {
        if (!storageKey) return;

        localStorage.setItem(storageKey, JSON.stringify({
            position: currentTime.value,
            timestamp: Date.now(),
        }));
    }, DEBOUNCE.autosave);

    function loadSavedPosition(): void {
        if (!storageKey || !videoRef.value) return;

        const saved = safeJsonParse<SavedProgress>(
            localStorage.getItem(storageKey) || '{}',
            { position: 0, timestamp: 0 }
        );

        // Only restore if saved within last 7 days
        const sevenDaysMs = 7 * 24 * 60 * 60 * 1000;
        if (saved.position > 0 && (Date.now() - saved.timestamp) < sevenDaysMs) {
            videoRef.value.currentTime = saved.position;
        }
    }

    function clearSavedPosition(): void {
        if (storageKey) {
            localStorage.removeItem(storageKey);
        }
    }

    // =============================================================================
    // Event Handlers
    // =============================================================================

    function handlePlay(): void {
        isPlaying.value = true;
    }

    function handlePause(): void {
        isPlaying.value = false;
    }

    function handleTimeUpdate(): void {
        if (!videoRef.value) return;

        currentTime.value = videoRef.value.currentTime;
        savePosition();
        onProgress?.(progress.value, currentTime.value);

        // Check for completion
        if (!hasCompleted.value && progress.value >= completionThreshold) {
            hasCompleted.value = true;
            onComplete?.();
        }
    }

    function handleLoadedMetadata(): void {
        if (!videoRef.value) return;

        duration.value = videoRef.value.duration;
        isLoading.value = false;
        loadSavedPosition();
    }

    function handleProgress(): void {
        if (!videoRef.value || !videoRef.value.buffered.length) return;

        buffered.value = videoRef.value.buffered.end(
            videoRef.value.buffered.length - 1
        );
    }

    function handleEnded(): void {
        isPlaying.value = false;
        clearSavedPosition();
        if (!hasCompleted.value) {
            hasCompleted.value = true;
            onComplete?.();
        }
    }

    function handleError(): void {
        error.value = 'Gagal memuat video. Silakan coba lagi.';
        isLoading.value = false;
    }

    function handleWaiting(): void {
        isLoading.value = true;
    }

    function handleCanPlay(): void {
        isLoading.value = false;
    }

    function handleFullscreenChange(): void {
        isFullscreen.value = !!document.fullscreenElement;
    }

    function handleVolumeChange(): void {
        if (videoRef.value) {
            volume.value = videoRef.value.volume;
            isMuted.value = videoRef.value.muted;
        }
    }

    // =============================================================================
    // Lifecycle
    // =============================================================================

    function setupListeners(): void {
        const video = videoRef.value;
        if (!video) return;

        video.addEventListener('play', handlePlay);
        video.addEventListener('pause', handlePause);
        video.addEventListener('timeupdate', handleTimeUpdate);
        video.addEventListener('loadedmetadata', handleLoadedMetadata);
        video.addEventListener('progress', handleProgress);
        video.addEventListener('ended', handleEnded);
        video.addEventListener('error', handleError);
        video.addEventListener('waiting', handleWaiting);
        video.addEventListener('canplay', handleCanPlay);
        video.addEventListener('volumechange', handleVolumeChange);
        document.addEventListener('fullscreenchange', handleFullscreenChange);
    }

    function cleanupListeners(): void {
        const video = videoRef.value;
        if (!video) return;

        video.removeEventListener('play', handlePlay);
        video.removeEventListener('pause', handlePause);
        video.removeEventListener('timeupdate', handleTimeUpdate);
        video.removeEventListener('loadedmetadata', handleLoadedMetadata);
        video.removeEventListener('progress', handleProgress);
        video.removeEventListener('ended', handleEnded);
        video.removeEventListener('error', handleError);
        video.removeEventListener('waiting', handleWaiting);
        video.removeEventListener('canplay', handleCanPlay);
        video.removeEventListener('volumechange', handleVolumeChange);
        document.removeEventListener('fullscreenchange', handleFullscreenChange);
    }

    // Watch for video element changes
    watch(videoRef, (newRef, oldRef) => {
        if (oldRef) cleanupListeners();
        if (newRef) setupListeners();
    });

    onMounted(() => {
        if (videoRef.value) setupListeners();
    });

    onUnmounted(() => {
        cleanupListeners();
    });

    // =============================================================================
    // Return
    // =============================================================================

    return {
        // State
        isPlaying,
        currentTime,
        duration,
        buffered,
        volume,
        isMuted,
        playbackRate,
        isFullscreen,
        isLoading,
        error,
        hasCompleted,

        // Computed
        progress,
        bufferedProgress,
        formattedCurrentTime,
        formattedDuration,
        canPlay,

        // Controls
        play,
        pause,
        togglePlay,
        seek,
        seekRelative,
        seekToPercent,
        setVolume,
        toggleMute,
        setPlaybackRate,
        toggleFullscreen,

        // Progress
        clearSavedPosition,
    };
}
