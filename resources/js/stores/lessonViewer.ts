// =============================================================================
// Lesson Viewer Store (Provide/Inject Pattern)
// State management for lesson viewing interface
// =============================================================================

import {
    provide,
    inject,
    ref,
    computed,
    readonly,
    type InjectionKey,
    type Ref,
    type ComputedRef,
} from 'vue';
import { router } from '@inertiajs/vue3';
import type { Lesson, LessonProgress, CourseSection, Course } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface LessonNavigationItem {
    id: number;
    title: string;
    content_type: Lesson['content_type'];
    is_completed?: boolean;
    section_title?: string;
}

interface LessonViewerState {
    /** Current lesson being viewed */
    lesson: Ref<Lesson>;
    /** Course containing the lesson */
    course: Ref<Course>;
    /** Progress data for the lesson */
    progress: Ref<LessonProgress | null>;
    /** All sections with lessons (for navigation) */
    sections: Ref<CourseSection[]>;
    /** Current media position (for video/audio) */
    mediaPosition: Ref<number>;
    /** Current page (for paginated content) */
    currentPage: Ref<number>;
    /** Total pages (for paginated content) */
    totalPages: Ref<number>;
    /** Whether progress save is pending */
    isSaving: Ref<boolean>;
}

interface LessonViewerComputed {
    /** Whether the lesson is completed */
    isCompleted: ComputedRef<boolean>;
    /** Progress percentage (0-100) */
    progressPercentage: ComputedRef<number>;
    /** Previous lesson in sequence */
    previousLesson: ComputedRef<LessonNavigationItem | null>;
    /** Next lesson in sequence */
    nextLesson: ComputedRef<LessonNavigationItem | null>;
    /** Current lesson's section */
    currentSection: ComputedRef<CourseSection | null>;
    /** Flattened list of all lessons for navigation */
    allLessons: ComputedRef<LessonNavigationItem[]>;
    /** Current lesson index in the course */
    currentLessonIndex: ComputedRef<number>;
    /** Total lessons in the course */
    totalLessons: ComputedRef<number>;
    /** Content type checks */
    isMediaContent: ComputedRef<boolean>;
    isPaginatedContent: ComputedRef<boolean>;
    isTextContent: ComputedRef<boolean>;
    isVideoContent: ComputedRef<boolean>;
}

interface LessonViewerActions {
    /** Update progress locally */
    updateLocalProgress: (updates: Partial<LessonProgress>) => void;
    /** Mark lesson as completed */
    markCompleted: () => Promise<boolean>;
    /** Save media position */
    saveMediaPosition: (seconds: number) => void;
    /** Save current page */
    saveCurrentPage: (page: number) => void;
    /** Navigate to a specific lesson */
    navigateToLesson: (lessonId: number) => void;
    /** Navigate to previous lesson */
    goToPrevious: () => void;
    /** Navigate to next lesson */
    goToNext: () => void;
    /** Set lesson data (from Inertia) */
    setLesson: (lesson: Lesson) => void;
    /** Set progress data (from Inertia) */
    setProgress: (progress: LessonProgress | null) => void;
}

type LessonViewerContext = LessonViewerState & LessonViewerComputed & LessonViewerActions;

// =============================================================================
// Injection Key
// =============================================================================

const LessonViewerKey: InjectionKey<LessonViewerContext> = Symbol('LessonViewer');

// =============================================================================
// Provider
// =============================================================================

export function provideLessonViewer(
    initialLesson: Lesson,
    initialCourse: Course,
    initialProgress: LessonProgress | null,
    initialSections: CourseSection[]
): LessonViewerContext {
    // =============================================================================
    // State
    // =============================================================================

    const lesson = ref<Lesson>({ ...initialLesson });
    const course = ref<Course>({ ...initialCourse });
    const progress = ref<LessonProgress | null>(
        initialProgress ? { ...initialProgress } : null
    );
    const sections = ref<CourseSection[]>(initialSections);
    const mediaPosition = ref(initialProgress?.media_position_seconds ?? 0);
    const currentPage = ref(initialProgress?.current_page ?? 1);
    const totalPages = ref(initialProgress?.total_pages ?? 1);
    const isSaving = ref(false);

    // =============================================================================
    // Computed
    // =============================================================================

    const isCompleted = computed(() =>
        progress.value?.is_completed ?? false
    );

    const progressPercentage = computed(() =>
        progress.value?.progress_percentage ?? 0
    );

    const allLessons = computed<LessonNavigationItem[]>(() => {
        const result: LessonNavigationItem[] = [];

        for (const section of sections.value) {
            for (const l of section.lessons ?? []) {
                result.push({
                    id: l.id,
                    title: l.title,
                    content_type: l.content_type,
                    is_completed: l.is_completed,
                    section_title: section.title,
                });
            }
        }

        return result;
    });

    const currentLessonIndex = computed(() =>
        allLessons.value.findIndex(l => l.id === lesson.value.id)
    );

    const totalLessons = computed(() =>
        allLessons.value.length
    );

    const previousLesson = computed<LessonNavigationItem | null>(() => {
        const index = currentLessonIndex.value;
        return index > 0 ? allLessons.value[index - 1] : null;
    });

    const nextLesson = computed<LessonNavigationItem | null>(() => {
        const index = currentLessonIndex.value;
        return index < allLessons.value.length - 1 ? allLessons.value[index + 1] : null;
    });

    const currentSection = computed<CourseSection | null>(() =>
        sections.value.find(s =>
            s.lessons?.some(l => l.id === lesson.value.id)
        ) ?? null
    );

    const isMediaContent = computed(() =>
        ['video', 'youtube', 'audio'].includes(lesson.value.content_type)
    );

    const isPaginatedContent = computed(() =>
        ['text', 'document'].includes(lesson.value.content_type)
    );

    const isTextContent = computed(() =>
        lesson.value.content_type === 'text'
    );

    const isVideoContent = computed(() =>
        ['video', 'youtube'].includes(lesson.value.content_type)
    );

    // =============================================================================
    // Actions
    // =============================================================================

    function updateLocalProgress(updates: Partial<LessonProgress>): void {
        if (progress.value) {
            progress.value = { ...progress.value, ...updates };
        } else {
            progress.value = {
                lesson_id: lesson.value.id,
                is_completed: false,
                progress_percentage: 0,
                media_position_seconds: 0,
                current_page: 1,
                total_pages: 1,
                ...updates,
            } as LessonProgress;
        }
    }

    async function markCompleted(): Promise<boolean> {
        if (isCompleted.value) return true;

        isSaving.value = true;

        return new Promise((resolve) => {
            router.post(
                `/courses/${course.value.id}/lessons/${lesson.value.id}/complete`,
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        updateLocalProgress({
                            is_completed: true,
                            progress_percentage: 100,
                            completed_at: new Date().toISOString(),
                        });
                        isSaving.value = false;
                        resolve(true);
                    },
                    onError: () => {
                        isSaving.value = false;
                        resolve(false);
                    },
                }
            );
        });
    }

    function saveMediaPosition(seconds: number): void {
        mediaPosition.value = seconds;
        updateLocalProgress({
            media_position_seconds: seconds,
        });

        // Debounced save to server would happen through useLessonProgress composable
    }

    function saveCurrentPage(page: number): void {
        currentPage.value = page;
        updateLocalProgress({
            current_page: page,
        });
    }

    function navigateToLesson(lessonId: number): void {
        router.visit(`/courses/${course.value.id}/lessons/${lessonId}`, {
            preserveScroll: false,
        });
    }

    function goToPrevious(): void {
        if (previousLesson.value) {
            navigateToLesson(previousLesson.value.id);
        }
    }

    function goToNext(): void {
        if (nextLesson.value) {
            navigateToLesson(nextLesson.value.id);
        }
    }

    function setLesson(newLesson: Lesson): void {
        lesson.value = newLesson;
    }

    function setProgress(newProgress: LessonProgress | null): void {
        progress.value = newProgress;
        mediaPosition.value = newProgress?.media_position_seconds ?? 0;
        currentPage.value = newProgress?.current_page ?? 1;
        totalPages.value = newProgress?.total_pages ?? 1;
    }

    // =============================================================================
    // Context
    // =============================================================================

    const context: LessonViewerContext = {
        // State
        lesson,
        course,
        progress,
        sections,
        mediaPosition,
        currentPage,
        totalPages,
        isSaving: readonly(isSaving),

        // Computed
        isCompleted,
        progressPercentage,
        previousLesson,
        nextLesson,
        currentSection,
        allLessons,
        currentLessonIndex,
        totalLessons,
        isMediaContent,
        isPaginatedContent,
        isTextContent,
        isVideoContent,

        // Actions
        updateLocalProgress,
        markCompleted,
        saveMediaPosition,
        saveCurrentPage,
        navigateToLesson,
        goToPrevious,
        goToNext,
        setLesson,
        setProgress,
    };

    provide(LessonViewerKey, context);

    return context;
}

// =============================================================================
// Consumer Hook
// =============================================================================

export function useLessonViewer(): LessonViewerContext {
    const context = inject(LessonViewerKey);

    if (!context) {
        throw new Error(
            'useLessonViewer must be used within a component that calls provideLessonViewer'
        );
    }

    return context;
}

// =============================================================================
// Optional: Check if context exists (non-throwing version)
// =============================================================================

export function useLessonViewerOptional(): LessonViewerContext | null {
    return inject(LessonViewerKey) ?? null;
}
