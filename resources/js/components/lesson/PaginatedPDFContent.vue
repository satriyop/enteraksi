<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { ZoomIn, ZoomOut, RotateCw, Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import PageNavigation from './PageNavigation.vue';

// PDF.js types
interface PDFDocumentProxy {
    numPages: number;
    getPage(pageNumber: number): Promise<PDFPageProxy>;
}

interface PDFPageProxy {
    getViewport(options: { scale: number; rotation?: number }): PDFPageViewport;
    render(options: { canvasContext: CanvasRenderingContext2D; viewport: PDFPageViewport }): { promise: Promise<void> };
}

interface PDFPageViewport {
    width: number;
    height: number;
}

interface Props {
    pdfUrl: string;
    initialPage?: number;
    courseId: number;
    lessonId: number;
}

const props = withDefaults(defineProps<Props>(), {
    initialPage: 1,
});

const emit = defineEmits<{
    'page-change': [page: number, total: number];
    'document-loaded': [totalPages: number];
}>();

// State
const currentPage = ref(props.initialPage);
const totalPages = ref(0);
const scale = ref(1.0);
const rotation = ref(0);
const isLoading = ref(true);
const isRendering = ref(false);
const loadError = ref<string | null>(null);
const slideDirection = ref<'left' | 'right'>('right');
const isSaving = ref(false);

// PDF.js instances
let pdfDoc: PDFDocumentProxy | null = null;
let pdfjsLib: typeof import('pdfjs-dist') | null = null;

// Canvas refs
const canvasRef = ref<HTMLCanvasElement | null>(null);
const containerRef = ref<HTMLElement | null>(null);

// Computed
const progressPercentage = computed(() => {
    if (totalPages.value === 0) return 0;
    return Math.round((currentPage.value / totalPages.value) * 100);
});

const canGoNext = computed(() => currentPage.value < totalPages.value);
const canGoPrev = computed(() => currentPage.value > 1);

// Load PDF.js from CDN
const loadPdfJs = async () => {
    if (pdfjsLib) return pdfjsLib;

    // Load PDF.js from CDN
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';

    await new Promise<void>((resolve, reject) => {
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load PDF.js'));
        document.head.appendChild(script);
    });

    // @ts-ignore - PDF.js adds itself to window
    pdfjsLib = window.pdfjsLib;

    // Set worker
    // @ts-ignore
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    return pdfjsLib;
};

// Load PDF document
const loadDocument = async () => {
    isLoading.value = true;
    loadError.value = null;

    try {
        const lib = await loadPdfJs();
        if (!lib) throw new Error('PDF.js not loaded');

        const loadingTask = lib.getDocument(props.pdfUrl);
        pdfDoc = await loadingTask.promise;
        totalPages.value = pdfDoc.numPages;

        // Ensure initial page is within bounds
        if (currentPage.value > totalPages.value) {
            currentPage.value = totalPages.value;
        }

        emit('document-loaded', totalPages.value);

        // Set loading to false FIRST so the canvas container is rendered
        isLoading.value = false;

        // Wait for DOM to update, then render
        await nextTick();

        // Small delay to ensure layout is complete
        await new Promise(resolve => setTimeout(resolve, 50));

        // Render first page
        await renderPage(currentPage.value);
    } catch (error) {
        console.error('Failed to load PDF:', error);
        loadError.value = 'Gagal memuat dokumen PDF. Silakan coba lagi.';
        isLoading.value = false;
    }
};

// Render a specific page
const renderPage = async (pageNum: number, retryCount = 0) => {
    if (!pdfDoc || !canvasRef.value || !containerRef.value) return;

    // Wait for next tick to ensure DOM is ready
    await nextTick();

    // Get container width, retry if it's 0 (not yet laid out)
    let containerWidth = containerRef.value.offsetWidth - 32; // Padding

    if (containerWidth <= 0 && retryCount < 3) {
        // Container not ready yet, wait and retry
        await new Promise(resolve => setTimeout(resolve, 100));
        return renderPage(pageNum, retryCount + 1);
    }

    // Fallback to a reasonable default if still 0
    if (containerWidth <= 0) {
        containerWidth = 800;
    }

    isRendering.value = true;

    try {
        const page = await pdfDoc.getPage(pageNum);

        // Calculate scale to fit container width
        const viewport = page.getViewport({ scale: 1.0, rotation: rotation.value });
        const fitScale = containerWidth / viewport.width;
        const finalScale = fitScale * scale.value;

        const scaledViewport = page.getViewport({ scale: finalScale, rotation: rotation.value });

        // Set canvas dimensions
        const canvas = canvasRef.value;
        const context = canvas.getContext('2d');
        if (!context) return;

        canvas.height = scaledViewport.height;
        canvas.width = scaledViewport.width;

        // Render
        await page.render({
            canvasContext: context,
            viewport: scaledViewport,
        }).promise;
    } catch (error) {
        console.error('Failed to render page:', error);
    } finally {
        isRendering.value = false;
    }
};

// Navigation
const goToPage = async (page: number) => {
    if (page < 1 || page > totalPages.value || isRendering.value) return;

    slideDirection.value = page > currentPage.value ? 'right' : 'left';
    currentPage.value = page;
    emit('page-change', page, totalPages.value);
    await renderPage(page);
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

// Zoom controls
const zoomIn = () => {
    scale.value = Math.min(scale.value + 0.25, 3.0);
    renderPage(currentPage.value);
};

const zoomOut = () => {
    scale.value = Math.max(scale.value - 0.25, 0.5);
    renderPage(currentPage.value);
};

const resetZoom = () => {
    scale.value = 1.0;
    renderPage(currentPage.value);
};

const rotate = () => {
    rotation.value = (rotation.value + 90) % 360;
    renderPage(currentPage.value);
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
        prevPage();
    } else {
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
        case '+':
        case '=':
            event.preventDefault();
            zoomIn();
            break;
        case '-':
            event.preventDefault();
            zoomOut();
            break;
        case '0':
            event.preventDefault();
            resetZoom();
            break;
    }
};

// Handle resize
let resizeTimeout: ReturnType<typeof setTimeout> | null = null;
const handleResize = () => {
    if (resizeTimeout) {
        clearTimeout(resizeTimeout);
    }
    resizeTimeout = setTimeout(() => {
        renderPage(currentPage.value);
    }, 250);
};

// Lifecycle
onMounted(() => {
    loadDocument();
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

// Watch for URL changes
watch(() => props.pdfUrl, () => {
    loadDocument();
});
</script>

<template>
    <div ref="containerRef" class="paginated-pdf-content">
        <!-- Loading state -->
        <div v-if="isLoading" class="min-h-[60vh] flex items-center justify-center">
            <div class="text-center">
                <Loader2 class="h-8 w-8 animate-spin mx-auto mb-2 text-primary" />
                <p class="text-sm text-muted-foreground">Memuat dokumen...</p>
            </div>
        </div>

        <!-- Error state -->
        <div v-else-if="loadError" class="min-h-[60vh] flex items-center justify-center">
            <div class="text-center">
                <p class="text-destructive mb-4">{{ loadError }}</p>
                <Button variant="outline" @click="loadDocument">
                    Coba Lagi
                </Button>
            </div>
        </div>

        <!-- PDF Viewer -->
        <template v-else>
            <!-- Toolbar -->
            <div class="flex items-center justify-between gap-2 mb-4 p-2 bg-muted/50 rounded-lg">
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        @click="zoomOut"
                        :disabled="scale <= 0.5"
                        title="Perkecil (−)"
                    >
                        <ZoomOut class="h-4 w-4" />
                    </Button>
                    <span class="text-sm text-muted-foreground min-w-[50px] text-center">
                        {{ Math.round(scale * 100) }}%
                    </span>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        @click="zoomIn"
                        :disabled="scale >= 3.0"
                        title="Perbesar (+)"
                    >
                        <ZoomIn class="h-4 w-4" />
                    </Button>
                </div>

                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8"
                    @click="rotate"
                    title="Putar"
                >
                    <RotateCw class="h-4 w-4" />
                </Button>
            </div>

            <!-- Canvas container -->
            <div
                class="relative overflow-auto bg-muted/30 rounded-lg flex items-start justify-center p-4"
                :style="{ maxHeight: '70vh' }"
                @touchstart.passive="handleTouchStart"
                @touchend.passive="handleTouchEnd"
            >
                <!-- Rendering overlay -->
                <div
                    v-if="isRendering"
                    class="absolute inset-0 bg-background/50 flex items-center justify-center z-10"
                >
                    <Loader2 class="h-6 w-6 animate-spin text-primary" />
                </div>

                <canvas
                    ref="canvasRef"
                    class="shadow-lg transition-opacity duration-200"
                    :class="{ 'opacity-50': isRendering }"
                />
            </div>

            <!-- Navigation -->
            <div v-if="totalPages > 1" class="mt-6 pt-4 border-t">
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
            <div v-else-if="totalPages === 1" class="mt-6 pt-4 border-t">
                <p class="text-center text-sm text-muted-foreground">
                    Dokumen ini hanya memiliki 1 halaman
                </p>
            </div>
        </template>
    </div>
</template>
