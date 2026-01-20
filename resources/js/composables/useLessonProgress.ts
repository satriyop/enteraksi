// =============================================================================
// useLessonProgress Composable
// Handles lesson progress tracking and saving
// =============================================================================

import axios from 'axios';
import { ref, onUnmounted } from 'vue';
import type { LessonProgress } from '@/types';

interface UseLessonProgressOptions {
    courseId: number;
    lessonId: number;
    enrollmentId: number | null;
    initialProgress: LessonProgress | null;
    initialCourseProgress: number;
}

export function useLessonProgress(options: UseLessonProgressOptions) {
    const { courseId, lessonId, enrollmentId, initialProgress, initialCourseProgress } = options;

    // =============================================================================
    // State
    // =============================================================================

    const isLessonCompleted = ref(initialProgress?.is_completed ?? false);
    const courseProgressPercentage = ref(initialCourseProgress);
    const isSavingProgress = ref(false);
    let saveDebounceTimer: ReturnType<typeof setTimeout> | null = null;
    let mediaProgressTimer: ReturnType<typeof setTimeout> | null = null;

    // =============================================================================
    // Progress Saving Methods
    // =============================================================================

    const saveProgress = async (page: number, total: number, metadata?: Record<string, unknown>) => {
        if (!enrollmentId || isSavingProgress.value) return;

        isSavingProgress.value = true;
        try {
            const response = await axios.patch(`/courses/${courseId}/lessons/${lessonId}/progress`, {
                current_page: page,
                total_pages: total,
                pagination_metadata: metadata ?? null,
            });

            if (response.data.enrollment?.progress_percentage !== undefined) {
                courseProgressPercentage.value = response.data.enrollment.progress_percentage;
            }
        } catch (error) {
            console.error('Failed to save progress:', error);
        } finally {
            isSavingProgress.value = false;
        }
    };

    const debouncedSaveProgress = (page: number, total: number, metadata?: Record<string, unknown>) => {
        if (saveDebounceTimer) clearTimeout(saveDebounceTimer);
        saveDebounceTimer = setTimeout(() => saveProgress(page, total, metadata), 500);
    };

    const saveMediaProgress = async (positionSeconds: number, durationSeconds: number) => {
        if (!enrollmentId || isSavingProgress.value || isLessonCompleted.value) return;

        isSavingProgress.value = true;
        try {
            const response = await axios.patch(`/courses/${courseId}/lessons/${lessonId}/progress/media`, {
                position_seconds: Math.floor(positionSeconds),
                duration_seconds: Math.floor(durationSeconds),
            });

            if (response.data.enrollment?.progress_percentage !== undefined) {
                courseProgressPercentage.value = response.data.enrollment.progress_percentage;
            }
            if (response.data.progress?.is_completed) {
                isLessonCompleted.value = true;
            }
        } catch (error) {
            console.error('Failed to save media progress:', error);
        } finally {
            isSavingProgress.value = false;
        }
    };

    const debouncedSaveMediaProgress = (positionSeconds: number, durationSeconds: number) => {
        if (mediaProgressTimer) clearTimeout(mediaProgressTimer);
        mediaProgressTimer = setTimeout(() => saveMediaProgress(positionSeconds, durationSeconds), 5000);
    };

    // =============================================================================
    // Event Handlers
    // =============================================================================

    const handlePageChange = (page: number, total: number) => {
        debouncedSaveProgress(page, total);
    };

    const handlePaginationReady = (totalPages: number, metadata: Record<string, unknown>) => {
        const page = initialProgress?.current_page ?? 1;
        saveProgress(page, totalPages, metadata);
    };

    const handleDocumentLoaded = (totalPages: number) => {
        const page = initialProgress?.current_page ?? 1;
        saveProgress(page, totalPages);
    };

    const handleMediaTimeUpdate = (currentTime: number, duration: number) => {
        debouncedSaveMediaProgress(currentTime, duration);
    };

    const handleMediaPause = () => {
        if (mediaProgressTimer) clearTimeout(mediaProgressTimer);
    };

    // =============================================================================
    // Cleanup
    // =============================================================================

    const cleanup = () => {
        if (saveDebounceTimer) clearTimeout(saveDebounceTimer);
        if (mediaProgressTimer) clearTimeout(mediaProgressTimer);
    };

    onUnmounted(cleanup);

    // =============================================================================
    // Return
    // =============================================================================

    return {
        isLessonCompleted,
        courseProgressPercentage,
        isSavingProgress,
        handlePageChange,
        handlePaginationReady,
        handleDocumentLoaded,
        handleMediaTimeUpdate,
        handleMediaPause,
    };
}
