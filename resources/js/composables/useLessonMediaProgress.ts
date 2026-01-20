// =============================================================================
// useLessonMediaProgress Composable
// Handles progress tracking for video, audio, and YouTube content
// =============================================================================

import { ref, onUnmounted } from 'vue';
import axios from 'axios';

// =============================================================================
// Types
// =============================================================================

interface UseLessonMediaProgressOptions {
    /** Course ID */
    courseId: number;
    /** Lesson ID */
    lessonId: number;
    /** Callback when lesson is auto-completed */
    onComplete?: () => void;
    /** Progress update interval in milliseconds */
    updateIntervalMs?: number;
}

interface MediaProgressResponse {
    progress?: {
        is_completed: boolean;
    };
    enrollment?: {
        progress_percentage: number;
    };
}

// =============================================================================
// Composable
// =============================================================================

export function useLessonMediaProgress(options: UseLessonMediaProgressOptions) {
    const {
        courseId,
        lessonId,
        onComplete,
        updateIntervalMs = 5000,
    } = options;

    // State
    const isSaving = ref(false);
    const isCompleted = ref(false);
    const courseProgress = ref(0);

    // Timer for debounced saves
    let saveTimer: ReturnType<typeof setTimeout> | null = null;

    /**
     * Save media progress to server
     */
    const saveProgress = async (positionSeconds: number, durationSeconds: number): Promise<void> => {
        if (isSaving.value || isCompleted.value) return;

        isSaving.value = true;

        try {
            const response = await axios.patch<MediaProgressResponse>(
                `/courses/${courseId}/lessons/${lessonId}/progress/media`,
                {
                    position_seconds: Math.floor(positionSeconds),
                    duration_seconds: Math.floor(durationSeconds),
                }
            );

            // Update course progress from response
            if (response.data.enrollment?.progress_percentage !== undefined) {
                courseProgress.value = response.data.enrollment.progress_percentage;
            }

            // Check if lesson was auto-completed
            if (response.data.progress?.is_completed) {
                isCompleted.value = true;
                onComplete?.();
            }
        } catch (error) {
            console.error('Failed to save media progress:', error);
        } finally {
            isSaving.value = false;
        }
    };

    /**
     * Debounced progress save - saves at most once per interval
     */
    const debouncedSaveProgress = (positionSeconds: number, durationSeconds: number): void => {
        if (saveTimer) {
            clearTimeout(saveTimer);
        }
        saveTimer = setTimeout(() => {
            saveProgress(positionSeconds, durationSeconds);
        }, updateIntervalMs);
    };

    /**
     * Handle time update from media player
     */
    const handleTimeUpdate = (currentTime: number, duration: number): void => {
        if (duration > 0 && !isNaN(duration)) {
            debouncedSaveProgress(currentTime, duration);
        }
    };

    /**
     * Handle pause event - save immediately
     */
    const handlePause = (currentTime: number, duration: number): void => {
        if (duration > 0 && !isNaN(duration)) {
            // Cancel debounced save and save immediately
            if (saveTimer) {
                clearTimeout(saveTimer);
            }
            saveProgress(currentTime, duration);
        }
    };

    /**
     * Handle media ended event
     */
    const handleEnded = (duration: number): void => {
        // Save final position
        saveProgress(duration, duration);
    };

    /**
     * Clean up on unmount
     */
    onUnmounted(() => {
        if (saveTimer) {
            clearTimeout(saveTimer);
        }
    });

    return {
        // State
        isSaving,
        isCompleted,
        courseProgress,

        // Methods
        saveProgress,
        debouncedSaveProgress,
        handleTimeUpdate,
        handlePause,
        handleEnded,
    };
}
