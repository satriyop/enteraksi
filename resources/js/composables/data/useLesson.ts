// =============================================================================
// useLesson Composable
// Single lesson data and navigation management
// =============================================================================

import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import type {
    Lesson,
    LessonWithNavigation,
    LessonProgress,
    LessonNavItem,
    ContentType,
} from '@/types';

// =============================================================================
// Types
// =============================================================================

interface UseLessonOptions {
    /** Initial lesson data (from Inertia page props) */
    initial?: Lesson | LessonWithNavigation;
    /** Initial progress data */
    progress?: LessonProgress | null;
    /** Course ID for navigation */
    courseId?: number;
}

// =============================================================================
// Composable
// =============================================================================

export function useLesson(options: UseLessonOptions = {}) {
    const { initial, progress: initialProgress, courseId } = options;

    // =============================================================================
    // State
    // =============================================================================

    const lesson = ref<Lesson | LessonWithNavigation | null>(initial ?? null);
    const progress = ref<LessonProgress | null>(initialProgress ?? null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // =============================================================================
    // Computed
    // =============================================================================

    const lessonId = computed(() => lesson.value?.id ?? null);

    const contentType = computed<ContentType | null>(() =>
        lesson.value?.content_type ?? null
    );

    const isTextContent = computed(() => contentType.value === 'text');
    const isVideoContent = computed(() => contentType.value === 'video');
    const isYouTubeContent = computed(() => contentType.value === 'youtube');
    const isAudioContent = computed(() => contentType.value === 'audio');
    const isDocumentContent = computed(() => contentType.value === 'document');
    const isConferenceContent = computed(() => contentType.value === 'conference');

    const isMediaContent = computed(() =>
        ['video', 'youtube', 'audio'].includes(contentType.value ?? '')
    );

    const isPaginatedContent = computed(() =>
        ['text', 'document'].includes(contentType.value ?? '')
    );

    const isCompleted = computed(() =>
        progress.value?.is_completed ?? lesson.value?.is_completed ?? false
    );

    const progressPercentage = computed(() =>
        progress.value?.progress_percentage ?? 0
    );

    const duration = computed(() =>
        lesson.value?.estimated_duration_minutes ?? 0
    );

    const isFreePreview = computed(() =>
        lesson.value?.is_free_preview ?? false
    );

    // Navigation
    const previousLesson = computed<LessonNavItem | null>(() => {
        if (lesson.value && 'previous_lesson' in lesson.value) {
            return lesson.value.previous_lesson ?? null;
        }
        return null;
    });

    const nextLesson = computed<LessonNavItem | null>(() => {
        if (lesson.value && 'next_lesson' in lesson.value) {
            return lesson.value.next_lesson ?? null;
        }
        return null;
    });

    const hasPreviousLesson = computed(() => previousLesson.value !== null);
    const hasNextLesson = computed(() => nextLesson.value !== null);

    // Media
    const youtubeVideoId = computed(() =>
        lesson.value?.youtube_video_id ?? null
    );

    const mediaUrl = computed(() => {
        if (!lesson.value?.media?.length) return null;

        // Find the primary media file based on content type
        const media = lesson.value.media[0];
        return media?.url ?? null;
    });

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Navigate to a specific lesson
     */
    function navigateToLesson(lessonId: number): void {
        if (!courseId) {
            console.warn('courseId not provided for navigation');
            return;
        }

        router.visit(`/courses/${courseId}/lessons/${lessonId}`, {
            preserveScroll: false,
        });
    }

    /**
     * Navigate to previous lesson
     */
    function goToPreviousLesson(): void {
        if (previousLesson.value) {
            navigateToLesson(previousLesson.value.id);
        }
    }

    /**
     * Navigate to next lesson
     */
    function goToNextLesson(): void {
        if (nextLesson.value) {
            navigateToLesson(nextLesson.value.id);
        }
    }

    /**
     * Set lesson data (from Inertia props)
     */
    function setLesson(newLesson: Lesson | LessonWithNavigation | null): void {
        lesson.value = newLesson;
    }

    /**
     * Set progress data (from Inertia props)
     */
    function setProgress(newProgress: LessonProgress | null): void {
        progress.value = newProgress;
    }

    /**
     * Update local progress (optimistic update)
     */
    function updateLocalProgress(updates: Partial<LessonProgress>): void {
        if (progress.value) {
            progress.value = { ...progress.value, ...updates };
        }
    }

    /**
     * Mark lesson as completed locally
     */
    function markCompletedLocally(): void {
        if (progress.value) {
            progress.value.is_completed = true;
            progress.value.completed_at = new Date().toISOString();
        }
        if (lesson.value) {
            lesson.value.is_completed = true;
        }
    }

    /**
     * Get resume position for media content
     */
    function getResumePosition(): number {
        return progress.value?.media_position_seconds ?? 0;
    }

    /**
     * Get resume page for paginated content
     */
    function getResumePage(): number {
        return progress.value?.current_page ?? 1;
    }

    // =============================================================================
    // Return
    // =============================================================================

    return {
        // State
        lesson,
        progress,
        isLoading,
        error,

        // Computed - Basic
        lessonId,
        contentType,
        duration,
        isFreePreview,

        // Computed - Content Type Checks
        isTextContent,
        isVideoContent,
        isYouTubeContent,
        isAudioContent,
        isDocumentContent,
        isConferenceContent,
        isMediaContent,
        isPaginatedContent,

        // Computed - Progress
        isCompleted,
        progressPercentage,

        // Computed - Navigation
        previousLesson,
        nextLesson,
        hasPreviousLesson,
        hasNextLesson,

        // Computed - Media
        youtubeVideoId,
        mediaUrl,

        // Methods
        navigateToLesson,
        goToPreviousLesson,
        goToNextLesson,
        setLesson,
        setProgress,
        updateLocalProgress,
        markCompletedLocally,
        getResumePosition,
        getResumePage,
    };
}
