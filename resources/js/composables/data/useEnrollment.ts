// =============================================================================
// useEnrollment Composable
// Enrollment data and progress management
// =============================================================================

import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import type {
    Enrollment,
    EnrollmentWithProgress,
    LessonProgress,
} from '@/types';

// =============================================================================
// Types
// =============================================================================

interface UseEnrollmentOptions {
    /** Initial enrollment data (from Inertia page props) */
    initial?: Enrollment | EnrollmentWithProgress | null;
}

// =============================================================================
// Composable
// =============================================================================

export function useEnrollment(options: UseEnrollmentOptions = {}) {
    const { initial } = options;

    // State
    const enrollment = ref<EnrollmentWithProgress | Enrollment | null>(
        initial ?? null
    );
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // =============================================================================
    // Computed
    // =============================================================================

    const enrollmentId = computed(() => enrollment.value?.id ?? null);

    const progressPercentage = computed(() =>
        enrollment.value?.progress_percentage ?? 0
    );

    const isEnrolled = computed(() => enrollment.value !== null);

    const isCompleted = computed(() =>
        enrollment.value?.status === 'completed'
    );

    const isActive = computed(() =>
        enrollment.value?.status === 'active'
    );

    const hasStarted = computed(() =>
        enrollment.value?.started_at !== null
    );

    const completedLessonsCount = computed(() => {
        if (!enrollment.value) return 0;
        if ('completed_lessons_count' in enrollment.value) {
            return enrollment.value.completed_lessons_count;
        }
        if ('lesson_progress' in enrollment.value && enrollment.value.lesson_progress) {
            return enrollment.value.lesson_progress.filter(
                lp => lp.is_completed
            ).length;
        }
        return 0;
    });

    const totalLessonsCount = computed(() => {
        if (!enrollment.value) return 0;
        if ('total_lessons_count' in enrollment.value) {
            return enrollment.value.total_lessons_count;
        }
        if (enrollment.value.course) {
            return enrollment.value.course.total_lessons ?? 0;
        }
        return 0;
    });

    const currentLessonId = computed(() => {
        if (!enrollment.value) return null;
        if ('current_lesson_id' in enrollment.value) {
            return enrollment.value.current_lesson_id ?? null;
        }
        return enrollment.value.last_lesson_id ?? null;
    });

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Get progress for a specific lesson
     */
    function getLessonProgress(lessonId: number): LessonProgress | null {
        if (!enrollment.value || !('lesson_progress' in enrollment.value)) {
            return null;
        }
        return enrollment.value.lesson_progress?.find(
            lp => lp.lesson_id === lessonId
        ) ?? null;
    }

    /**
     * Check if a lesson is completed
     */
    function isLessonCompleted(lessonId: number): boolean {
        const progress = getLessonProgress(lessonId);
        return progress?.is_completed ?? false;
    }

    /**
     * Check if a lesson is accessible (based on sequential progress)
     */
    function isLessonAccessible(lessonId: number, lessonIndex: number): boolean {
        // First lesson is always accessible
        if (lessonIndex === 0) return true;

        // If no enrollment, only first lesson is accessible
        if (!enrollment.value) return false;

        // Check if lesson is completed or in progress
        const progress = getLessonProgress(lessonId);
        if (progress) return true;

        // Check if previous lessons are completed (simplified - real implementation
        // might need the full lesson list)
        return true; // Allow access by default, let backend enforce
    }

    /**
     * Enroll in a course
     */
    async function enroll(courseId: number): Promise<boolean> {
        isLoading.value = true;
        error.value = null;

        return new Promise((resolve) => {
            router.post(`/courses/${courseId}/enroll`, {}, {
                onSuccess: (page) => {
                    // Update enrollment from response if available
                    if (page.props.enrollment) {
                        enrollment.value = page.props.enrollment as EnrollmentWithProgress;
                    }
                    resolve(true);
                },
                onError: (errors) => {
                    error.value = errors.message || 'Gagal mendaftar kursus';
                    resolve(false);
                },
                onFinish: () => {
                    isLoading.value = false;
                },
            });
        });
    }

    /**
     * Unenroll from a course
     */
    async function unenroll(courseId: number): Promise<boolean> {
        isLoading.value = true;
        error.value = null;

        return new Promise((resolve) => {
            router.delete(`/courses/${courseId}/unenroll`, {
                onSuccess: () => {
                    enrollment.value = null;
                    resolve(true);
                },
                onError: (errors) => {
                    error.value = errors.message || 'Gagal keluar dari kursus';
                    resolve(false);
                },
                onFinish: () => {
                    isLoading.value = false;
                },
            });
        });
    }

    /**
     * Set enrollment (from Inertia props)
     */
    function setEnrollment(
        newEnrollment: Enrollment | EnrollmentWithProgress | null
    ): void {
        enrollment.value = newEnrollment;
    }

    /**
     * Update local progress (optimistic update)
     */
    function updateLocalProgress(lessonId: number, progress: Partial<LessonProgress>): void {
        if (!enrollment.value || !('lesson_progress' in enrollment.value)) {
            return;
        }

        const existingProgress = enrollment.value.lesson_progress?.find(
            lp => lp.lesson_id === lessonId
        );

        if (existingProgress) {
            Object.assign(existingProgress, progress);
        } else if (enrollment.value.lesson_progress) {
            enrollment.value.lesson_progress.push({
                lesson_id: lessonId,
                is_completed: false,
                current_page: 1,
                total_pages: null,
                highest_page_reached: 1,
                ...progress,
            } as LessonProgress);
        }

        // Recalculate progress percentage
        if (enrollment.value.lesson_progress && totalLessonsCount.value > 0) {
            const completedCount = enrollment.value.lesson_progress.filter(
                lp => lp.is_completed
            ).length;
            enrollment.value.progress_percentage = Math.round(
                (completedCount / totalLessonsCount.value) * 100
            );
        }
    }

    return {
        // State
        enrollment,
        isLoading,
        error,

        // Computed
        enrollmentId,
        progressPercentage,
        isEnrolled,
        isCompleted,
        isActive,
        hasStarted,
        completedLessonsCount,
        totalLessonsCount,
        currentLessonId,

        // Methods
        getLessonProgress,
        isLessonCompleted,
        isLessonAccessible,
        enroll,
        unenroll,
        setEnrollment,
        updateLocalProgress,
    };
}
