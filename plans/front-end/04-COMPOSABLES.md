# Phase 4: Composables Strategy

## Overview

This phase addresses the critical gap of only 4 composables in the entire codebase. Composables are the Vue 3 way to extract and reuse stateful logic across components. Without them, business logic is duplicated in components, making maintenance difficult.

**Duration:** 2-3 weeks
**Risk Level:** Medium
**Dependencies:** Phase 1 (Type System), Phase 2 (Utilities)

---

## Current State Analysis

### Existing Composables (Only 4)
```
resources/js/composables/
├── useAppearance.ts    # Theme handling
├── useInitials.ts      # User initials
├── useMobileNav.ts     # Mobile navigation
└── useSidebarState.ts  # Sidebar collapse
```

### Missing Composables (Critical Gap)
| Category | Missing | Impact |
|----------|---------|--------|
| Data Fetching | useCourse, useLesson, useAssessment | Logic duplicated in pages |
| Forms | useCourseForm, useAssessmentForm | Form logic reimplemented |
| Features | useProgressTracking, useGrading | Business logic scattered |
| UI | useModal, useToast, useConfirmation | UI state duplicated |

---

## Target Architecture

### Directory Structure
```
resources/js/composables/
├── data/                    # Data fetching & caching
│   ├── useCourse.ts
│   ├── useCourses.ts       # List with pagination
│   ├── useLesson.ts
│   ├── useAssessment.ts
│   ├── useEnrollment.ts
│   └── useUser.ts
├── forms/                   # Form handling
│   ├── useCourseForm.ts
│   ├── useLessonForm.ts
│   ├── useAssessmentForm.ts
│   └── useQuestionForm.ts
├── features/                # Feature-specific logic
│   ├── useProgressTracking.ts
│   ├── useAssessmentAttempt.ts
│   ├── useGrading.ts
│   ├── useVideoPlayer.ts
│   └── useFileUpload.ts
├── ui/                      # UI state management
│   ├── useModal.ts
│   ├── useToast.ts
│   ├── useConfirmation.ts
│   ├── usePagination.ts
│   └── useSearch.ts
└── index.ts                 # Re-exports
```

---

## Implementation Steps

### Step 1: Create Data Fetching Composables

**File: `composables/data/useCourse.ts`**
```typescript
import { ref, computed, watch, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { Course, CourseWithSections, CourseFilters } from '@/types';
import { show } from '@/actions/App/Http/Controllers/CourseController';

interface UseCourseOptions {
    /** Initial course data (from Inertia page props) */
    initial?: Course;
    /** Whether to fetch sections */
    withSections?: boolean;
}

interface UseCourseReturn {
    course: Ref<Course | null>;
    isLoading: Ref<boolean>;
    error: Ref<string | null>;
    fetch: (id: number) => Promise<void>;
    refresh: () => Promise<void>;
    updateLocal: (updates: Partial<Course>) => void;
}

export function useCourse(options: UseCourseOptions = {}): UseCourseReturn {
    const { initial, withSections = false } = options;

    const course = ref<Course | null>(initial ?? null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    async function fetch(id: number): Promise<void> {
        isLoading.value = true;
        error.value = null;

        try {
            // Use Inertia visit for SPA navigation
            await router.visit(show.url(id), {
                preserveState: true,
                preserveScroll: true,
                only: ['course', 'sections'],
                onSuccess: (page) => {
                    course.value = page.props.course as Course;
                },
                onError: (errors) => {
                    error.value = 'Gagal memuat kursus';
                    console.error(errors);
                },
            });
        } finally {
            isLoading.value = false;
        }
    }

    async function refresh(): Promise<void> {
        if (course.value?.id) {
            await fetch(course.value.id);
        }
    }

    function updateLocal(updates: Partial<Course>): void {
        if (course.value) {
            course.value = { ...course.value, ...updates };
        }
    }

    return {
        course,
        isLoading,
        error,
        fetch,
        refresh,
        updateLocal,
    };
}

/**
 * Composable for fetching paginated courses list
 */
export function useCourses(initialFilters: CourseFilters = {}) {
    const courses = ref<Course[]>([]);
    const filters = ref<CourseFilters>(initialFilters);
    const isLoading = ref(false);
    const pagination = ref({
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
    });

    const hasMore = computed(() =>
        pagination.value.currentPage < pagination.value.lastPage
    );

    async function fetch(): Promise<void> {
        isLoading.value = true;

        await router.visit('/courses', {
            method: 'get',
            data: {
                ...filters.value,
                page: pagination.value.currentPage,
            },
            preserveState: true,
            preserveScroll: true,
            only: ['courses'],
            onSuccess: (page) => {
                const response = page.props.courses;
                courses.value = response.data;
                pagination.value = {
                    currentPage: response.meta.current_page,
                    lastPage: response.meta.last_page,
                    perPage: response.meta.per_page,
                    total: response.meta.total,
                };
            },
        });

        isLoading.value = false;
    }

    function setFilters(newFilters: Partial<CourseFilters>): void {
        filters.value = { ...filters.value, ...newFilters };
        pagination.value.currentPage = 1;
        fetch();
    }

    function nextPage(): void {
        if (hasMore.value) {
            pagination.value.currentPage++;
            fetch();
        }
    }

    function previousPage(): void {
        if (pagination.value.currentPage > 1) {
            pagination.value.currentPage--;
            fetch();
        }
    }

    function goToPage(page: number): void {
        if (page >= 1 && page <= pagination.value.lastPage) {
            pagination.value.currentPage = page;
            fetch();
        }
    }

    return {
        courses,
        filters,
        pagination,
        isLoading,
        hasMore,
        fetch,
        setFilters,
        nextPage,
        previousPage,
        goToPage,
    };
}
```

**File: `composables/data/useEnrollment.ts`**
```typescript
import { ref, computed, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { Enrollment, EnrollmentWithProgress, LessonProgress } from '@/types';

interface UseEnrollmentOptions {
    initial?: Enrollment | EnrollmentWithProgress;
}

export function useEnrollment(options: UseEnrollmentOptions = {}) {
    const enrollment = ref<EnrollmentWithProgress | null>(
        options.initial as EnrollmentWithProgress ?? null
    );
    const isLoading = ref(false);

    const progressPercentage = computed(() =>
        enrollment.value?.progress_percentage ?? 0
    );

    const isCompleted = computed(() =>
        enrollment.value?.status === 'completed'
    );

    const completedLessons = computed(() =>
        enrollment.value?.lesson_progress?.filter(
            lp => lp.status === 'completed'
        ).length ?? 0
    );

    const totalLessons = computed(() =>
        enrollment.value?.total_lessons_count ?? 0
    );

    const currentLessonId = computed(() =>
        enrollment.value?.current_lesson_id
    );

    function getLessonProgress(lessonId: number): LessonProgress | undefined {
        return enrollment.value?.lesson_progress?.find(
            lp => lp.lesson_id === lessonId
        );
    }

    function isLessonCompleted(lessonId: number): boolean {
        const progress = getLessonProgress(lessonId);
        return progress?.status === 'completed';
    }

    function isLessonAccessible(lessonId: number, lessonPosition: number): boolean {
        if (!enrollment.value) return false;

        // First lesson is always accessible
        if (lessonPosition === 0) return true;

        // Check if previous lesson is completed
        const progress = enrollment.value.lesson_progress ?? [];
        // Logic depends on your business rules
        return true; // Simplified
    }

    async function enroll(courseId: number): Promise<boolean> {
        isLoading.value = true;

        return new Promise((resolve) => {
            router.post(`/courses/${courseId}/enroll`, {}, {
                onSuccess: () => {
                    resolve(true);
                },
                onError: () => {
                    resolve(false);
                },
                onFinish: () => {
                    isLoading.value = false;
                },
            });
        });
    }

    return {
        enrollment,
        isLoading,
        progressPercentage,
        isCompleted,
        completedLessons,
        totalLessons,
        currentLessonId,
        getLessonProgress,
        isLessonCompleted,
        isLessonAccessible,
        enroll,
    };
}
```

### Step 2: Create Feature Composables

**File: `composables/features/useProgressTracking.ts`**
```typescript
import { ref, computed, watch, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import type { LessonProgress, EnrollmentWithProgress } from '@/types';
import { debounce } from '@/lib/utils';
import { DEBOUNCE } from '@/lib/constants';

interface UseProgressTrackingOptions {
    enrollment: EnrollmentWithProgress;
    lessonId: number;
    /** Auto-save interval in ms (0 to disable) */
    autoSaveInterval?: number;
}

export function useProgressTracking(options: UseProgressTrackingOptions) {
    const { enrollment, lessonId, autoSaveInterval = 30000 } = options;

    // Local state
    const currentProgress = ref<LessonProgress>(
        enrollment.lesson_progress?.find(lp => lp.lesson_id === lessonId) ?? {
            lesson_id: lessonId,
            status: 'not_started',
            progress_percentage: 0,
            time_spent: 0,
            completed_at: null,
        }
    );

    const isSaving = ref(false);
    const lastSavedAt = ref<Date | null>(null);
    const hasUnsavedChanges = ref(false);

    // Track time spent
    const startTime = ref(Date.now());
    const sessionTimeSpent = ref(0);

    // Update session time every second
    const timeTracker = setInterval(() => {
        sessionTimeSpent.value = Math.floor((Date.now() - startTime.value) / 1000);
    }, 1000);

    // Computed
    const isCompleted = computed(() =>
        currentProgress.value.status === 'completed'
    );

    const progressPercentage = computed(() =>
        currentProgress.value.progress_percentage
    );

    const totalTimeSpent = computed(() =>
        currentProgress.value.time_spent + sessionTimeSpent.value
    );

    // Debounced save function
    const debouncedSave = debounce(saveProgress, DEBOUNCE.autosave);

    async function saveProgress(): Promise<void> {
        if (isSaving.value) return;

        isSaving.value = true;
        hasUnsavedChanges.value = false;

        try {
            await router.post(`/lessons/${lessonId}/progress`, {
                progress_percentage: currentProgress.value.progress_percentage,
                time_spent: totalTimeSpent.value,
                last_position: currentProgress.value.last_position,
            }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    lastSavedAt.value = new Date();
                },
                onError: () => {
                    hasUnsavedChanges.value = true;
                },
            });
        } finally {
            isSaving.value = false;
        }
    }

    function updateProgress(percentage: number, position?: number): void {
        currentProgress.value.progress_percentage = Math.max(
            currentProgress.value.progress_percentage,
            percentage
        );

        if (position !== undefined) {
            currentProgress.value.last_position = position;
        }

        if (currentProgress.value.status === 'not_started') {
            currentProgress.value.status = 'in_progress';
        }

        hasUnsavedChanges.value = true;
        debouncedSave();
    }

    async function markCompleted(): Promise<void> {
        currentProgress.value.status = 'completed';
        currentProgress.value.progress_percentage = 100;
        currentProgress.value.completed_at = new Date().toISOString();

        await saveProgress();

        // Notify completion to trigger course progress recalculation
        router.post(`/lessons/${lessonId}/complete`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    // Auto-save interval
    let autoSaveTimer: ReturnType<typeof setInterval> | null = null;

    if (autoSaveInterval > 0) {
        autoSaveTimer = setInterval(() => {
            if (hasUnsavedChanges.value) {
                saveProgress();
            }
        }, autoSaveInterval);
    }

    // Save before unload
    function handleBeforeUnload(event: BeforeUnloadEvent): void {
        if (hasUnsavedChanges.value) {
            saveProgress();
            event.preventDefault();
            event.returnValue = '';
        }
    }

    window.addEventListener('beforeunload', handleBeforeUnload);

    // Cleanup
    onUnmounted(() => {
        clearInterval(timeTracker);
        if (autoSaveTimer) clearInterval(autoSaveTimer);
        window.removeEventListener('beforeunload', handleBeforeUnload);

        // Final save
        if (hasUnsavedChanges.value) {
            saveProgress();
        }
    });

    return {
        currentProgress,
        isCompleted,
        progressPercentage,
        totalTimeSpent,
        sessionTimeSpent,
        isSaving,
        lastSavedAt,
        hasUnsavedChanges,
        updateProgress,
        markCompleted,
        saveProgress,
    };
}
```

**File: `composables/features/useAssessmentAttempt.ts`**
```typescript
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import type {
    Assessment,
    AssessmentAttempt,
    Question,
    SubmitAttemptData
} from '@/types';
import { formatPlaybackTime } from '@/lib/formatters';

interface UseAssessmentAttemptOptions {
    assessment: Assessment;
    attempt: AssessmentAttempt;
    questions: Question[];
}

export function useAssessmentAttempt(options: UseAssessmentAttemptOptions) {
    const { assessment, attempt, questions } = options;

    // State
    const answers = ref<Record<number, string | string[] | Record<string, string>>>({});
    const currentQuestionIndex = ref(0);
    const isSubmitting = ref(false);
    const timeRemaining = ref(0);
    const isTimeUp = ref(false);

    // Initialize answers from attempt
    if (attempt.answers) {
        attempt.answers.forEach(a => {
            answers.value[a.question_id] = a.answer;
        });
    }

    // Computed
    const currentQuestion = computed(() =>
        questions[currentQuestionIndex.value]
    );

    const totalQuestions = computed(() => questions.length);

    const answeredCount = computed(() =>
        Object.keys(answers.value).length
    );

    const unansweredQuestions = computed(() =>
        questions.filter(q => !answers.value[q.id])
    );

    const isAllAnswered = computed(() =>
        answeredCount.value === totalQuestions.value
    );

    const canSubmit = computed(() =>
        answeredCount.value > 0 && !isSubmitting.value
    );

    const timeRemainingFormatted = computed(() =>
        formatPlaybackTime(timeRemaining.value)
    );

    const progressPercentage = computed(() =>
        (answeredCount.value / totalQuestions.value) * 100
    );

    // Navigation
    function nextQuestion(): void {
        if (currentQuestionIndex.value < questions.length - 1) {
            currentQuestionIndex.value++;
        }
    }

    function previousQuestion(): void {
        if (currentQuestionIndex.value > 0) {
            currentQuestionIndex.value--;
        }
    }

    function goToQuestion(index: number): void {
        if (index >= 0 && index < questions.length) {
            currentQuestionIndex.value = index;
        }
    }

    // Answer handling
    function setAnswer(
        questionId: number,
        answer: string | string[] | Record<string, string>
    ): void {
        answers.value[questionId] = answer;
        saveAnswersToServer();
    }

    // Auto-save answers (debounced in real implementation)
    async function saveAnswersToServer(): Promise<void> {
        await router.post(`/attempts/${attempt.id}/save`, {
            answers: Object.entries(answers.value).map(([qId, answer]) => ({
                question_id: parseInt(qId),
                answer,
            })),
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    // Submit attempt
    async function submit(): Promise<void> {
        if (!canSubmit.value) return;

        isSubmitting.value = true;

        const submitData: SubmitAttemptData = {
            answers: Object.entries(answers.value).map(([qId, answer]) => ({
                question_id: parseInt(qId),
                answer,
            })),
        };

        await router.post(`/attempts/${attempt.id}/submit`, submitData, {
            onSuccess: () => {
                // Redirect will be handled by server
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        });
    }

    // Timer
    let timerInterval: ReturnType<typeof setInterval> | null = null;

    function startTimer(): void {
        if (!assessment.time_limit) return;

        // Calculate remaining time
        const startTime = new Date(attempt.started_at).getTime();
        const limitMs = assessment.time_limit * 60 * 1000;
        const elapsed = Date.now() - startTime;
        timeRemaining.value = Math.max(0, Math.floor((limitMs - elapsed) / 1000));

        timerInterval = setInterval(() => {
            timeRemaining.value--;

            if (timeRemaining.value <= 0) {
                isTimeUp.value = true;
                stopTimer();
                // Auto-submit when time is up
                submit();
            }
        }, 1000);
    }

    function stopTimer(): void {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    // Lifecycle
    onMounted(() => {
        if (assessment.time_limit) {
            startTimer();
        }
    });

    onUnmounted(() => {
        stopTimer();
    });

    return {
        // State
        answers,
        currentQuestionIndex,
        isSubmitting,
        timeRemaining,
        timeRemainingFormatted,
        isTimeUp,

        // Computed
        currentQuestion,
        totalQuestions,
        answeredCount,
        unansweredQuestions,
        isAllAnswered,
        canSubmit,
        progressPercentage,

        // Actions
        nextQuestion,
        previousQuestion,
        goToQuestion,
        setAnswer,
        submit,
    };
}
```

**File: `composables/features/useVideoPlayer.ts`**
```typescript
import { ref, computed, watch, onMounted, onUnmounted, type Ref } from 'vue';
import { STORAGE_KEYS, DEBOUNCE } from '@/lib/constants';
import { debounce, safeJsonParse } from '@/lib/utils';

interface UseVideoPlayerOptions {
    videoRef: Ref<HTMLVideoElement | null>;
    lessonId: number;
    onProgress?: (percentage: number, position: number) => void;
    onComplete?: () => void;
}

export function useVideoPlayer(options: UseVideoPlayerOptions) {
    const { videoRef, lessonId, onProgress, onComplete } = options;

    // State
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

    // Storage key for this lesson
    const storageKey = `${STORAGE_KEYS.videoProgress}-${lessonId}`;

    // Computed
    const progress = computed(() =>
        duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0
    );

    const bufferedProgress = computed(() =>
        duration.value > 0 ? (buffered.value / duration.value) * 100 : 0
    );

    // Playback controls
    function play(): void {
        videoRef.value?.play();
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

        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            videoRef.value.requestFullscreen();
        }
    }

    // Progress saving
    const savePosition = debounce(() => {
        localStorage.setItem(storageKey, JSON.stringify({
            position: currentTime.value,
            timestamp: Date.now(),
        }));
    }, DEBOUNCE.autosave);

    function loadSavedPosition(): void {
        const saved = safeJsonParse<{ position: number; timestamp: number }>(
            localStorage.getItem(storageKey) || '{}',
            { position: 0, timestamp: 0 }
        );

        // Only restore if saved within last 7 days
        const sevenDaysMs = 7 * 24 * 60 * 60 * 1000;
        if (saved.position > 0 && (Date.now() - saved.timestamp) < sevenDaysMs) {
            if (videoRef.value) {
                videoRef.value.currentTime = saved.position;
            }
        }
    }

    function clearSavedPosition(): void {
        localStorage.removeItem(storageKey);
    }

    // Event handlers
    function handleTimeUpdate(): void {
        if (!videoRef.value) return;

        currentTime.value = videoRef.value.currentTime;
        savePosition();
        onProgress?.(progress.value, currentTime.value);
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
        clearSavedPosition();
        onComplete?.();
    }

    function handleFullscreenChange(): void {
        isFullscreen.value = !!document.fullscreenElement;
    }

    function handleError(): void {
        error.value = 'Gagal memuat video. Silakan coba lagi.';
        isLoading.value = false;
    }

    // Setup event listeners
    function setupListeners(): void {
        const video = videoRef.value;
        if (!video) return;

        video.addEventListener('play', () => isPlaying.value = true);
        video.addEventListener('pause', () => isPlaying.value = false);
        video.addEventListener('timeupdate', handleTimeUpdate);
        video.addEventListener('loadedmetadata', handleLoadedMetadata);
        video.addEventListener('progress', handleProgress);
        video.addEventListener('ended', handleEnded);
        video.addEventListener('error', handleError);
        video.addEventListener('waiting', () => isLoading.value = true);
        video.addEventListener('canplay', () => isLoading.value = false);
        document.addEventListener('fullscreenchange', handleFullscreenChange);
    }

    function cleanupListeners(): void {
        const video = videoRef.value;
        if (!video) return;

        video.removeEventListener('play', () => isPlaying.value = true);
        video.removeEventListener('pause', () => isPlaying.value = false);
        video.removeEventListener('timeupdate', handleTimeUpdate);
        video.removeEventListener('loadedmetadata', handleLoadedMetadata);
        video.removeEventListener('progress', handleProgress);
        video.removeEventListener('ended', handleEnded);
        video.removeEventListener('error', handleError);
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

        // Computed
        progress,
        bufferedProgress,

        // Controls
        play,
        pause,
        togglePlay,
        seek,
        seekRelative,
        setVolume,
        toggleMute,
        setPlaybackRate,
        toggleFullscreen,
    };
}
```

### Step 3: Create UI Composables

**File: `composables/ui/useModal.ts`**
```typescript
import { ref, readonly } from 'vue';

interface UseModalOptions {
    closeOnEscape?: boolean;
    closeOnClickOutside?: boolean;
}

export function useModal<T = unknown>(options: UseModalOptions = {}) {
    const { closeOnEscape = true, closeOnClickOutside = true } = options;

    const isOpen = ref(false);
    const data = ref<T | null>(null);

    function open(modalData?: T): void {
        data.value = modalData ?? null;
        isOpen.value = true;

        if (closeOnEscape) {
            document.addEventListener('keydown', handleEscape);
        }
    }

    function close(): void {
        isOpen.value = false;
        data.value = null;

        document.removeEventListener('keydown', handleEscape);
    }

    function toggle(modalData?: T): void {
        if (isOpen.value) {
            close();
        } else {
            open(modalData);
        }
    }

    function handleEscape(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            close();
        }
    }

    function handleClickOutside(): void {
        if (closeOnClickOutside) {
            close();
        }
    }

    return {
        isOpen: readonly(isOpen),
        data: readonly(data),
        open,
        close,
        toggle,
        handleClickOutside,
    };
}

/**
 * Confirmation modal composable
 */
export function useConfirmation() {
    const isOpen = ref(false);
    const message = ref('');
    const title = ref('Konfirmasi');
    const confirmLabel = ref('Ya');
    const cancelLabel = ref('Batal');
    const isDestructive = ref(false);

    let resolvePromise: ((value: boolean) => void) | null = null;

    interface ConfirmOptions {
        title?: string;
        message: string;
        confirmLabel?: string;
        cancelLabel?: string;
        destructive?: boolean;
    }

    function confirm(options: ConfirmOptions): Promise<boolean> {
        title.value = options.title ?? 'Konfirmasi';
        message.value = options.message;
        confirmLabel.value = options.confirmLabel ?? 'Ya';
        cancelLabel.value = options.cancelLabel ?? 'Batal';
        isDestructive.value = options.destructive ?? false;
        isOpen.value = true;

        return new Promise((resolve) => {
            resolvePromise = resolve;
        });
    }

    function handleConfirm(): void {
        isOpen.value = false;
        resolvePromise?.(true);
        resolvePromise = null;
    }

    function handleCancel(): void {
        isOpen.value = false;
        resolvePromise?.(false);
        resolvePromise = null;
    }

    return {
        isOpen: readonly(isOpen),
        title: readonly(title),
        message: readonly(message),
        confirmLabel: readonly(confirmLabel),
        cancelLabel: readonly(cancelLabel),
        isDestructive: readonly(isDestructive),
        confirm,
        handleConfirm,
        handleCancel,
    };
}
```

**File: `composables/ui/useToast.ts`**
```typescript
import { ref, computed, readonly } from 'vue';
import { TOAST_DURATION } from '@/lib/constants';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface Toast {
    id: number;
    type: ToastType;
    title: string;
    message?: string;
    duration: number;
}

interface ToastOptions {
    title: string;
    message?: string;
    duration?: number;
}

let toastIdCounter = 0;

// Global state (singleton pattern)
const toasts = ref<Toast[]>([]);

export function useToast() {
    function add(type: ToastType, options: ToastOptions): number {
        const id = ++toastIdCounter;
        const duration = options.duration ?? TOAST_DURATION.normal;

        const toast: Toast = {
            id,
            type,
            title: options.title,
            message: options.message,
            duration,
        };

        toasts.value.push(toast);

        if (duration > 0) {
            setTimeout(() => remove(id), duration);
        }

        return id;
    }

    function remove(id: number): void {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index > -1) {
            toasts.value.splice(index, 1);
        }
    }

    function clear(): void {
        toasts.value = [];
    }

    // Convenience methods
    function success(options: ToastOptions): number {
        return add('success', options);
    }

    function error(options: ToastOptions): number {
        return add('error', { ...options, duration: options.duration ?? TOAST_DURATION.long });
    }

    function warning(options: ToastOptions): number {
        return add('warning', options);
    }

    function info(options: ToastOptions): number {
        return add('info', options);
    }

    return {
        toasts: readonly(toasts),
        add,
        remove,
        clear,
        success,
        error,
        warning,
        info,
    };
}
```

**File: `composables/ui/useSearch.ts`**
```typescript
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from '@/lib/utils';
import { DEBOUNCE } from '@/lib/constants';

interface UseSearchOptions {
    /** Parameter name for the search query */
    paramName?: string;
    /** Debounce delay in ms */
    debounceMs?: number;
    /** Minimum characters before searching */
    minLength?: number;
    /** Initial search value */
    initial?: string;
    /** Only preserve these props during search */
    only?: string[];
}

export function useSearch(options: UseSearchOptions = {}) {
    const {
        paramName = 'search',
        debounceMs = DEBOUNCE.search,
        minLength = 1,
        initial = '',
        only,
    } = options;

    const query = ref(initial);
    const isSearching = ref(false);

    const hasQuery = computed(() => query.value.length >= minLength);

    const performSearch = debounce((searchQuery: string) => {
        isSearching.value = true;

        const data: Record<string, string | undefined> = {
            [paramName]: searchQuery || undefined,
        };

        router.get(window.location.pathname, data, {
            preserveState: true,
            preserveScroll: true,
            only,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    }, debounceMs);

    watch(query, (newQuery) => {
        if (newQuery.length === 0 || newQuery.length >= minLength) {
            performSearch(newQuery);
        }
    });

    function clear(): void {
        query.value = '';
    }

    function search(searchQuery: string): void {
        query.value = searchQuery;
    }

    return {
        query,
        isSearching,
        hasQuery,
        clear,
        search,
    };
}
```

**File: `composables/ui/usePagination.ts`**
```typescript
import { ref, computed, watch, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { PaginationMeta } from '@/types';

interface UsePaginationOptions {
    /** Initial pagination meta from props */
    initial: PaginationMeta;
    /** Parameter name for page */
    pageParam?: string;
    /** Parameter name for per_page */
    perPageParam?: string;
    /** Only preserve these props */
    only?: string[];
}

export function usePagination(options: UsePaginationOptions) {
    const {
        initial,
        pageParam = 'page',
        perPageParam = 'per_page',
        only,
    } = options;

    const meta = ref<PaginationMeta>(initial);

    const currentPage = computed(() => meta.value.current_page);
    const lastPage = computed(() => meta.value.last_page);
    const perPage = computed(() => meta.value.per_page);
    const total = computed(() => meta.value.total);
    const from = computed(() => meta.value.from);
    const to = computed(() => meta.value.to);

    const hasPreviousPage = computed(() => currentPage.value > 1);
    const hasNextPage = computed(() => currentPage.value < lastPage.value);

    const pages = computed(() => {
        const range: number[] = [];
        const delta = 2;
        const left = Math.max(1, currentPage.value - delta);
        const right = Math.min(lastPage.value, currentPage.value + delta);

        for (let i = left; i <= right; i++) {
            range.push(i);
        }

        // Add first page and ellipsis
        if (left > 1) {
            if (left > 2) {
                range.unshift(-1); // Ellipsis marker
            }
            range.unshift(1);
        }

        // Add last page and ellipsis
        if (right < lastPage.value) {
            if (right < lastPage.value - 1) {
                range.push(-1); // Ellipsis marker
            }
            range.push(lastPage.value);
        }

        return range;
    });

    function goToPage(page: number): void {
        if (page < 1 || page > lastPage.value || page === currentPage.value) {
            return;
        }

        router.get(window.location.pathname, {
            [pageParam]: page,
            [perPageParam]: perPage.value,
        }, {
            preserveState: true,
            preserveScroll: true,
            only,
        });
    }

    function nextPage(): void {
        if (hasNextPage.value) {
            goToPage(currentPage.value + 1);
        }
    }

    function previousPage(): void {
        if (hasPreviousPage.value) {
            goToPage(currentPage.value - 1);
        }
    }

    function setPerPage(newPerPage: number): void {
        router.get(window.location.pathname, {
            [pageParam]: 1, // Reset to first page
            [perPageParam]: newPerPage,
        }, {
            preserveState: true,
            preserveScroll: true,
            only,
        });
    }

    // Update meta when props change
    function updateMeta(newMeta: PaginationMeta): void {
        meta.value = newMeta;
    }

    return {
        meta,
        currentPage,
        lastPage,
        perPage,
        total,
        from,
        to,
        hasPreviousPage,
        hasNextPage,
        pages,
        goToPage,
        nextPage,
        previousPage,
        setPerPage,
        updateMeta,
    };
}
```

### Step 4: Create Index Export

**File: `composables/index.ts`**
```typescript
// Data composables
export { useCourse, useCourses } from './data/useCourse';
export { useEnrollment } from './data/useEnrollment';

// Feature composables
export { useProgressTracking } from './features/useProgressTracking';
export { useAssessmentAttempt } from './features/useAssessmentAttempt';
export { useVideoPlayer } from './features/useVideoPlayer';

// UI composables
export { useModal, useConfirmation } from './ui/useModal';
export { useToast } from './ui/useToast';
export { useSearch } from './ui/useSearch';
export { usePagination } from './ui/usePagination';

// Existing composables
export { useAppearance } from './useAppearance';
export { useInitials } from './useInitials';
export { useMobileNav } from './useMobileNav';
export { useSidebarState } from './useSidebarState';
```

---

## Composable Guidelines

### Naming Convention
| Type | Pattern | Example |
|------|---------|---------|
| Data | `use{Resource}` | `useCourse`, `useUser` |
| Data List | `use{Resource}s` | `useCourses`, `useUsers` |
| Feature | `use{Feature}` | `useProgressTracking` |
| Form | `use{Resource}Form` | `useCourseForm` |
| UI | `use{UIElement}` | `useModal`, `useToast` |

### Return Value Guidelines
```typescript
// Good: Consistent return structure
export function useSomething() {
    // State (reactive refs)
    const data = ref<Data | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Computed
    const isEmpty = computed(() => !data.value);

    // Actions
    async function fetch() { /* ... */ }
    function reset() { /* ... */ }

    return {
        // State first
        data,
        isLoading,
        error,
        // Computed
        isEmpty,
        // Actions last
        fetch,
        reset,
    };
}
```

### Error Handling
```typescript
// Good: Consistent error handling
async function fetch() {
    isLoading.value = true;
    error.value = null;

    try {
        // ... async operation
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unknown error';
    } finally {
        isLoading.value = false;
    }
}
```

---

## Checklist

### Data Composables
- [ ] Create `useCourse.ts`
- [ ] Create `useCourses.ts`
- [ ] Create `useLesson.ts`
- [ ] Create `useAssessment.ts`
- [ ] Create `useEnrollment.ts`
- [ ] Create `useUser.ts`

### Feature Composables
- [ ] Create `useProgressTracking.ts`
- [ ] Create `useAssessmentAttempt.ts`
- [ ] Create `useGrading.ts`
- [ ] Create `useVideoPlayer.ts`
- [ ] Create `useFileUpload.ts`

### UI Composables
- [ ] Create `useModal.ts`
- [ ] Create `useConfirmation.ts`
- [ ] Create `useToast.ts`
- [ ] Create `usePagination.ts`
- [ ] Create `useSearch.ts`

### Integration
- [ ] Create `index.ts` with all exports
- [ ] Migrate pages to use composables
- [ ] Remove duplicated logic from components
- [ ] Add TypeScript types to all composables

---

## Success Criteria

| Metric | Before | After |
|--------|--------|-------|
| Composables count | 4 | 20+ |
| Logic duplication | High | Minimal |
| Test coverage for logic | 0% | 80%+ |
| Type coverage | ~30% | 100% |

---

## Next Phase

After completing Composables Strategy, proceed to [Phase 5: State Management](./05-STATE-MANAGEMENT.md).
