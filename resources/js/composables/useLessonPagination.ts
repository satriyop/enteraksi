import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

interface LessonProgress {
    current_page: number;
    total_pages: number | null;
    highest_page_reached: number;
    is_completed: boolean;
    pagination_metadata: Record<string, unknown> | null;
}

interface UseLessonPaginationOptions {
    courseId: number;
    lessonId: number;
    initialPage?: number;
    totalPages?: number;
    savedMetadata?: Record<string, unknown> | null;
    autoSaveInterval?: number; // in seconds
}

export function useLessonPagination(options: UseLessonPaginationOptions) {
    const {
        courseId,
        lessonId,
        initialPage = 1,
        totalPages: initialTotalPages,
        savedMetadata = null,
        autoSaveInterval = 30,
    } = options;

    const currentPage = ref(initialPage);
    const totalPages = ref(initialTotalPages ?? 1);
    const highestPageReached = ref(initialPage);
    const isCompleted = ref(false);
    const isSaving = ref(false);
    const paginationMetadata = ref<Record<string, unknown> | null>(savedMetadata);

    // Direction for slide animation
    const slideDirection = ref<'left' | 'right'>('right');

    // Time tracking
    const startTime = ref(Date.now());
    const timeSpentSeconds = ref(0);

    // Auto-save timer
    let autoSaveTimer: ReturnType<typeof setInterval> | null = null;
    let saveDebounceTimer: ReturnType<typeof setTimeout> | null = null;

    const progressPercentage = computed(() => {
        if (totalPages.value === 0) return 0;
        return Math.round((highestPageReached.value / totalPages.value) * 100);
    });

    const canGoNext = computed(() => currentPage.value < totalPages.value);
    const canGoPrev = computed(() => currentPage.value > 1);
    const isFirstPage = computed(() => currentPage.value === 1);
    const isLastPage = computed(() => currentPage.value === totalPages.value);

    const saveProgress = async (immediate = false) => {
        if (isSaving.value && !immediate) return;

        // Calculate time spent since last save
        const now = Date.now();
        const additionalTime = (now - startTime.value) / 1000;
        startTime.value = now;

        isSaving.value = true;

        try {
            await axios.patch(`/courses/${courseId}/lessons/${lessonId}/progress`, {
                current_page: currentPage.value,
                total_pages: totalPages.value,
                pagination_metadata: paginationMetadata.value,
                time_spent_seconds: additionalTime,
            });
        } catch (error) {
            console.error('Failed to save progress:', error);
        } finally {
            isSaving.value = false;
        }
    };

    const debouncedSave = () => {
        if (saveDebounceTimer) {
            clearTimeout(saveDebounceTimer);
        }
        saveDebounceTimer = setTimeout(() => {
            saveProgress();
        }, 500);
    };

    const goToPage = (page: number) => {
        if (page < 1 || page > totalPages.value) return;

        // Set slide direction
        slideDirection.value = page > currentPage.value ? 'right' : 'left';

        currentPage.value = page;

        // Update highest page reached
        if (page > highestPageReached.value) {
            highestPageReached.value = page;
        }

        // Check for completion
        if (page === totalPages.value && !isCompleted.value) {
            isCompleted.value = true;
        }

        debouncedSave();
    };

    const nextPage = () => {
        if (canGoNext.value) {
            goToPage(currentPage.value + 1);
        }
    };

    const prevPage = () => {
        if (canGoPrev.value) {
            goToPage(currentPage.value - 1);
        }
    };

    const firstPage = () => {
        goToPage(1);
    };

    const lastPage = () => {
        goToPage(totalPages.value);
    };

    const setTotalPages = (total: number, metadata?: Record<string, unknown>) => {
        totalPages.value = total;
        if (metadata) {
            paginationMetadata.value = metadata;
        }

        // Ensure current page is within bounds
        if (currentPage.value > total) {
            currentPage.value = total;
        }

        debouncedSave();
    };

    const markCompleted = async () => {
        try {
            await axios.post(`/courses/${courseId}/lessons/${lessonId}/complete`);
            isCompleted.value = true;
        } catch (error) {
            console.error('Failed to mark as completed:', error);
        }
    };

    // Keyboard navigation
    const handleKeydown = (event: KeyboardEvent) => {
        // Don't handle if user is typing in an input
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
            return;
        }

        switch (event.key) {
            case 'ArrowRight':
            case 'd':
            case 'D':
                event.preventDefault();
                nextPage();
                break;
            case 'ArrowLeft':
            case 'a':
            case 'A':
                event.preventDefault();
                prevPage();
                break;
            case 'Home':
                event.preventDefault();
                firstPage();
                break;
            case 'End':
                event.preventDefault();
                lastPage();
                break;
        }
    };

    // Save on page unload
    const handleBeforeUnload = () => {
        // Use sendBeacon for reliable saving on unload
        const data = new FormData();
        data.append('current_page', currentPage.value.toString());
        data.append('total_pages', totalPages.value.toString());
        data.append('time_spent_seconds', ((Date.now() - startTime.value) / 1000).toString());
        if (paginationMetadata.value) {
            data.append('pagination_metadata', JSON.stringify(paginationMetadata.value));
        }
        data.append('_method', 'PATCH');

        navigator.sendBeacon(`/courses/${courseId}/lessons/${lessonId}/progress`, data);
    };

    // Save on Inertia navigation
    const handleInertiaStart = () => {
        saveProgress(true);
    };

    onMounted(() => {
        // Add keyboard listener
        document.addEventListener('keydown', handleKeydown);

        // Add unload listener
        window.addEventListener('beforeunload', handleBeforeUnload);

        // Add Inertia navigation listener
        router.on('before', handleInertiaStart);

        // Start auto-save timer
        if (autoSaveInterval > 0) {
            autoSaveTimer = setInterval(() => {
                saveProgress();
            }, autoSaveInterval * 1000);
        }
    });

    onUnmounted(() => {
        // Cleanup
        document.removeEventListener('keydown', handleKeydown);
        window.removeEventListener('beforeunload', handleBeforeUnload);

        if (autoSaveTimer) {
            clearInterval(autoSaveTimer);
        }
        if (saveDebounceTimer) {
            clearTimeout(saveDebounceTimer);
        }
    });

    return {
        // State
        currentPage,
        totalPages,
        highestPageReached,
        isCompleted,
        isSaving,
        paginationMetadata,
        slideDirection,

        // Computed
        progressPercentage,
        canGoNext,
        canGoPrev,
        isFirstPage,
        isLastPage,

        // Methods
        goToPage,
        nextPage,
        prevPage,
        firstPage,
        lastPage,
        setTotalPages,
        saveProgress,
        markCompleted,
    };
}
