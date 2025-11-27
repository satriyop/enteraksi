<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import PageNavigation from './PageNavigation.vue';

interface PaginationMetadata {
    viewportHeight: number;
    contentHeight: number;
    pageBreaks: number[];
}

interface Props {
    content: string;
    initialPage?: number;
    savedMetadata?: PaginationMetadata | null;
    courseId: number;
    lessonId: number;
}

const props = withDefaults(defineProps<Props>(), {
    initialPage: 1,
    savedMetadata: null,
});

const emit = defineEmits<{
    'page-change': [page: number, total: number];
    'pagination-ready': [totalPages: number, metadata: PaginationMetadata];
}>();

// Refs
const containerRef = ref<HTMLElement | null>(null);
const contentRef = ref<HTMLElement | null>(null);
const measureRef = ref<HTMLElement | null>(null);

// State
const currentPage = ref(props.initialPage);
const totalPages = ref(1);
const pages = ref<string[]>([]);
const isInitialized = ref(false);
const slideDirection = ref<'left' | 'right'>('right');
const isSaving = ref(false);

// Content height for pagination (70vh)
const maxContentHeight = ref(0);

// Page break indices (element indices where each page starts)
const pageBreaks = ref<number[]>([0]);

// Computed
const progressPercentage = computed(() => {
    if (totalPages.value === 0) return 0;
    return Math.round((currentPage.value / totalPages.value) * 100);
});

const canGoNext = computed(() => currentPage.value < totalPages.value);
const canGoPrev = computed(() => currentPage.value > 1);

const currentPageContent = computed(() => {
    return pages.value[currentPage.value - 1] || '';
});

// Methods
const calculateMaxHeight = () => {
    // Use 70vh for content height
    maxContentHeight.value = Math.floor(window.innerHeight * 0.7);
};

const paginateContent = async () => {
    if (!measureRef.value || !props.content) return;

    calculateMaxHeight();

    // Parse HTML content into elements
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = props.content;

    const elements = Array.from(tempDiv.children);
    if (elements.length === 0) {
        // If no block elements, wrap in a div
        pages.value = [props.content];
        totalPages.value = 1;
        isInitialized.value = true;
        emitPaginationReady();
        return;
    }

    // Measure each element's height
    measureRef.value.innerHTML = '';
    const elementHeights: number[] = [];

    for (const element of elements) {
        const clone = element.cloneNode(true) as HTMLElement;
        measureRef.value.appendChild(clone);
        await nextTick();
        elementHeights.push(clone.offsetHeight + 16); // Add margin
    }

    // Group elements into pages
    const newPages: string[] = [];
    const newPageBreaks: number[] = [0];
    let currentHeight = 0;
    let currentPageElements: Element[] = [];

    for (let i = 0; i < elements.length; i++) {
        const elementHeight = elementHeights[i];

        // Check if adding this element would exceed max height
        if (currentHeight + elementHeight > maxContentHeight.value && currentPageElements.length > 0) {
            // Save current page
            newPages.push(currentPageElements.map(el => el.outerHTML).join(''));
            newPageBreaks.push(i);

            // Start new page
            currentPageElements = [elements[i]];
            currentHeight = elementHeight;
        } else {
            currentPageElements.push(elements[i]);
            currentHeight += elementHeight;
        }
    }

    // Don't forget the last page
    if (currentPageElements.length > 0) {
        newPages.push(currentPageElements.map(el => el.outerHTML).join(''));
    }

    pages.value = newPages;
    pageBreaks.value = newPageBreaks;
    totalPages.value = newPages.length;

    // Ensure current page is within bounds
    if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
    }

    isInitialized.value = true;
    emitPaginationReady();
};

const emitPaginationReady = () => {
    const metadata: PaginationMetadata = {
        viewportHeight: window.innerHeight,
        contentHeight: maxContentHeight.value,
        pageBreaks: pageBreaks.value,
    };
    emit('pagination-ready', totalPages.value, metadata);
};

const goToPage = (page: number) => {
    if (page < 1 || page > totalPages.value) return;

    slideDirection.value = page > currentPage.value ? 'right' : 'left';
    currentPage.value = page;
    emit('page-change', page, totalPages.value);
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

// Touch/swipe handling
let touchStartX = 0;
let touchEndX = 0;
const minSwipeDistance = 50;

const handleTouchStart = (e: TouchEvent) => {
    touchStartX = e.changedTouches[0].screenX;
};

const handleTouchEnd = (e: TouchEvent) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
};

const handleSwipe = () => {
    const distance = touchEndX - touchStartX;

    if (Math.abs(distance) < minSwipeDistance) return;

    if (distance > 0) {
        // Swipe right = previous page
        prevPage();
    } else {
        // Swipe left = next page
        nextPage();
    }
};

// Keyboard navigation
const handleKeydown = (event: KeyboardEvent) => {
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

// Handle viewport resize
let resizeTimeout: ReturnType<typeof setTimeout> | null = null;
const handleResize = () => {
    if (resizeTimeout) {
        clearTimeout(resizeTimeout);
    }
    resizeTimeout = setTimeout(() => {
        const oldHeight = maxContentHeight.value;
        calculateMaxHeight();

        // Only re-paginate if height changed significantly (>100px)
        if (Math.abs(oldHeight - maxContentHeight.value) > 100) {
            paginateContent();
        }
    }, 250);
};

// Lifecycle
onMounted(async () => {
    await nextTick();
    await paginateContent();

    document.addEventListener('keydown', handleKeydown);
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('resize', handleResize);

    if (resizeTimeout) {
        clearTimeout(resizeTimeout);
    }
});

// Watch for content changes
watch(() => props.content, () => {
    paginateContent();
});
</script>

<template>
    <div ref="containerRef" class="paginated-text-content">
        <!-- Hidden measurement container -->
        <div
            ref="measureRef"
            class="prose prose-sm dark:prose-invert max-w-none absolute -left-[9999px] opacity-0 pointer-events-none"
            :style="{ width: containerRef?.offsetWidth + 'px' }"
            aria-hidden="true"
        />

        <!-- Loading state -->
        <div v-if="!isInitialized" class="min-h-[50vh] flex items-center justify-center">
            <div class="animate-pulse space-y-4 w-full">
                <div class="h-4 bg-muted rounded w-3/4" />
                <div class="h-4 bg-muted rounded w-full" />
                <div class="h-4 bg-muted rounded w-5/6" />
                <div class="h-4 bg-muted rounded w-2/3" />
            </div>
        </div>

        <!-- Content area with slide animation -->
        <div
            v-else
            ref="contentRef"
            class="relative overflow-hidden"
            :style="{ minHeight: maxContentHeight + 'px' }"
            @touchstart.passive="handleTouchStart"
            @touchend.passive="handleTouchEnd"
        >
            <Transition
                :name="slideDirection === 'right' ? 'slide-left' : 'slide-right'"
                mode="out-in"
            >
                <div
                    :key="currentPage"
                    class="prose prose-sm dark:prose-invert max-w-none"
                    v-html="currentPageContent"
                />
            </Transition>
        </div>

        <!-- Navigation -->
        <div v-if="isInitialized && totalPages > 1" class="mt-6 pt-4 border-t">
            <PageNavigation
                :current-page="currentPage"
                :total-pages="totalPages"
                :can-go-next="canGoNext"
                :can-go-prev="canGoPrev"
                :progress-percentage="progressPercentage"
                :is-saving="isSaving"
                @prev="prevPage"
                @next="nextPage"
                @first="firstPage"
                @last="lastPage"
            />
        </div>

        <!-- Single page indicator -->
        <div v-else-if="isInitialized && totalPages === 1" class="mt-6 pt-4 border-t">
            <p class="text-center text-sm text-muted-foreground">
                Konten ini hanya memiliki 1 halaman
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Slide left animation (next page) */
.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.3s ease-out;
}

.slide-left-enter-from {
    opacity: 0;
    transform: translateX(30px);
}

.slide-left-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}

/* Slide right animation (previous page) */
.slide-right-enter-active,
.slide-right-leave-active {
    transition: all 0.3s ease-out;
}

.slide-right-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}

.slide-right-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
