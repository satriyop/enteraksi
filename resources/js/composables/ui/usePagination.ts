// =============================================================================
// usePagination Composable
// Generic URL-based pagination for Inertia applications
// =============================================================================

import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// =============================================================================
// Types
// =============================================================================

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface UsePaginationOptions {
    /** Initial pagination meta from Inertia props */
    initial: PaginationMeta;
    /** URL parameter name for page */
    pageParam?: string;
    /** URL parameter name for per_page */
    perPageParam?: string;
    /** Only preserve these props during pagination */
    only?: string[];
}

// =============================================================================
// Composable
// =============================================================================

export function usePagination(options: UsePaginationOptions) {
    const {
        initial,
        pageParam = 'page',
        perPageParam = 'per_page',
        only,
    } = options;

    // State
    const meta = ref<PaginationMeta>(initial);

    // =============================================================================
    // Computed
    // =============================================================================

    const currentPage = computed(() => meta.value.current_page);
    const lastPage = computed(() => meta.value.last_page);
    const perPage = computed(() => meta.value.per_page);
    const total = computed(() => meta.value.total);
    const from = computed(() => meta.value.from);
    const to = computed(() => meta.value.to);

    const hasPreviousPage = computed(() => currentPage.value > 1);
    const hasNextPage = computed(() => currentPage.value < lastPage.value);

    /**
     * Generate page range for pagination UI
     * Returns numbers for pages, -1 for ellipsis markers
     */
    const pages = computed(() => {
        const range: number[] = [];
        const delta = 2; // Pages to show on each side of current
        const left = Math.max(1, currentPage.value - delta);
        const right = Math.min(lastPage.value, currentPage.value + delta);

        // Build the visible range
        for (let i = left; i <= right; i++) {
            range.push(i);
        }

        // Add first page and ellipsis if needed
        if (left > 1) {
            if (left > 2) {
                range.unshift(-1); // Ellipsis marker
            }
            range.unshift(1);
        }

        // Add last page and ellipsis if needed
        if (right < lastPage.value) {
            if (right < lastPage.value - 1) {
                range.push(-1); // Ellipsis marker
            }
            range.push(lastPage.value);
        }

        return range;
    });

    // =============================================================================
    // Navigation Methods
    // =============================================================================

    /**
     * Navigate to a specific page
     */
    function goToPage(page: number): void {
        if (page < 1 || page > lastPage.value || page === currentPage.value) {
            return;
        }

        // Get current URL params and merge with new page
        const url = new URL(window.location.href);
        url.searchParams.set(pageParam, page.toString());

        router.get(url.pathname + url.search, {}, {
            preserveState: true,
            preserveScroll: true,
            only,
        });
    }

    /**
     * Go to next page
     */
    function nextPage(): void {
        if (hasNextPage.value) {
            goToPage(currentPage.value + 1);
        }
    }

    /**
     * Go to previous page
     */
    function previousPage(): void {
        if (hasPreviousPage.value) {
            goToPage(currentPage.value - 1);
        }
    }

    /**
     * Change items per page (resets to page 1)
     */
    function setPerPage(newPerPage: number): void {
        const url = new URL(window.location.href);
        url.searchParams.set(pageParam, '1'); // Reset to first page
        url.searchParams.set(perPageParam, newPerPage.toString());

        router.get(url.pathname + url.search, {}, {
            preserveState: true,
            preserveScroll: true,
            only,
        });
    }

    /**
     * Update meta when props change (call in watch or onMounted)
     */
    function updateMeta(newMeta: PaginationMeta): void {
        meta.value = newMeta;
    }

    return {
        // State
        meta,

        // Computed
        currentPage,
        lastPage,
        perPage,
        total,
        from,
        to,
        hasPreviousPage,
        hasNextPage,
        pages,

        // Methods
        goToPage,
        nextPage,
        previousPage,
        setPerPage,
        updateMeta,
    };
}

// Re-export type for external use
export type { PaginationMeta };
