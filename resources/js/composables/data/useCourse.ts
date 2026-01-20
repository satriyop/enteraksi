// =============================================================================
// useCourse Composable
// Single course data management
// =============================================================================

import { ref, computed, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { Course, CourseWithCurriculum, CourseSection, Lesson } from '@/types';
import { show } from '@/actions/App/Http/Controllers/CourseController';

// =============================================================================
// Types
// =============================================================================

interface UseCourseOptions {
    /** Initial course data (from Inertia page props) */
    initial?: Course | CourseWithCurriculum;
}

// =============================================================================
// Composable
// =============================================================================

export function useCourse(options: UseCourseOptions = {}) {
    const { initial } = options;

    // State
    const course = ref<Course | CourseWithCurriculum | null>(initial ?? null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // =============================================================================
    // Computed
    // =============================================================================

    const courseId = computed(() => course.value?.id ?? null);
    const isPublished = computed(() => course.value?.status === 'published');
    const isDraft = computed(() => course.value?.status === 'draft');
    const isArchived = computed(() => course.value?.status === 'archived');
    const isPublic = computed(() => course.value?.visibility === 'public');

    const totalLessons = computed(() => {
        if (!course.value) return 0;
        if ('lessons_count' in course.value && course.value.lessons_count !== undefined) {
            return course.value.lessons_count;
        }
        // Count from sections if available
        if ('sections' in course.value && course.value.sections) {
            return course.value.sections.reduce((total, section) => {
                return total + (section.lessons?.length ?? section.lessons_count ?? 0);
            }, 0);
        }
        return course.value.total_lessons ?? 0;
    });

    const totalSections = computed(() => {
        if (!course.value) return 0;
        if ('sections' in course.value && course.value.sections) {
            return course.value.sections.length;
        }
        return course.value.sections_count ?? 0;
    });

    const duration = computed(() => {
        if (!course.value) return 0;
        return course.value.manual_duration_minutes ??
            course.value.estimated_duration_minutes ??
            course.value.duration ??
            0;
    });

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Fetch course data by ID
     */
    async function fetch(id: number): Promise<void> {
        isLoading.value = true;
        error.value = null;

        try {
            await router.visit(show.url(id), {
                preserveState: true,
                preserveScroll: true,
                only: ['course'],
                onSuccess: (page) => {
                    course.value = page.props.course as Course;
                },
                onError: () => {
                    error.value = 'Gagal memuat kursus';
                },
            });
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Refresh current course data
     */
    async function refresh(): Promise<void> {
        if (course.value?.id) {
            await fetch(course.value.id);
        }
    }

    /**
     * Update local course data (optimistic update)
     */
    function updateLocal(updates: Partial<Course>): void {
        if (course.value) {
            course.value = { ...course.value, ...updates };
        }
    }

    /**
     * Set course data (from Inertia props)
     */
    function setCourse(newCourse: Course | CourseWithCurriculum | null): void {
        course.value = newCourse;
    }

    /**
     * Get lesson by ID from curriculum
     */
    function getLesson(lessonId: number): Lesson | null {
        if (!course.value || !('sections' in course.value) || !course.value.sections) {
            return null;
        }

        for (const section of course.value.sections) {
            if (section.lessons) {
                const lesson = section.lessons.find(l => l.id === lessonId);
                if (lesson) return lesson;
            }
        }
        return null;
    }

    /**
     * Get section by ID from curriculum
     */
    function getSection(sectionId: number): CourseSection | null {
        if (!course.value || !('sections' in course.value) || !course.value.sections) {
            return null;
        }
        return course.value.sections.find(s => s.id === sectionId) ?? null;
    }

    /**
     * Find previous and next lessons for navigation
     */
    function getLessonNavigation(lessonId: number): {
        previous: Lesson | null;
        next: Lesson | null;
        currentIndex: number;
    } {
        if (!course.value || !('sections' in course.value) || !course.value.sections) {
            return { previous: null, next: null, currentIndex: -1 };
        }

        // Flatten all lessons
        const allLessons: Lesson[] = [];
        for (const section of course.value.sections) {
            if (section.lessons) {
                allLessons.push(...section.lessons);
            }
        }

        const currentIndex = allLessons.findIndex(l => l.id === lessonId);
        if (currentIndex === -1) {
            return { previous: null, next: null, currentIndex: -1 };
        }

        return {
            previous: currentIndex > 0 ? allLessons[currentIndex - 1] : null,
            next: currentIndex < allLessons.length - 1 ? allLessons[currentIndex + 1] : null,
            currentIndex,
        };
    }

    return {
        // State
        course,
        isLoading,
        error,

        // Computed
        courseId,
        isPublished,
        isDraft,
        isArchived,
        isPublic,
        totalLessons,
        totalSections,
        duration,

        // Methods
        fetch,
        refresh,
        updateLocal,
        setCourse,
        getLesson,
        getSection,
        getLessonNavigation,
    };
}
