// =============================================================================
// useCourses Composable
// Course list with filtering and pagination
// =============================================================================

import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import type { Course, CourseListItem, CourseFilters } from '@/types';
import { debounce } from '@/lib/utils';
import { DEBOUNCE } from '@/lib/constants';

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

interface UseCoursesOptions {
    /** Initial courses (from Inertia page props) */
    initial?: CourseListItem[] | Course[];
    /** Initial pagination meta */
    meta?: PaginationMeta;
    /** Initial filters */
    filters?: CourseFilters;
    /** Fetch URL */
    url?: string;
    /** Only preserve these props */
    only?: string[];
}

// =============================================================================
// Composable
// =============================================================================

export function useCourses(options: UseCoursesOptions = {}) {
    const {
        initial = [],
        meta: initialMeta,
        filters: initialFilters = {},
        url = '/courses',
        only = ['courses'],
    } = options;

    // State
    const courses = ref<(CourseListItem | Course)[]>(initial);
    const filters = ref<CourseFilters>(initialFilters);
    const isLoading = ref(false);
    const pagination = ref<PaginationMeta>(initialMeta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: null,
        to: null,
    });

    // =============================================================================
    // Computed
    // =============================================================================

    const hasMore = computed(() =>
        pagination.value.current_page < pagination.value.last_page
    );

    const isEmpty = computed(() => courses.value.length === 0);

    const totalCount = computed(() => pagination.value.total);

    const currentPage = computed(() => pagination.value.current_page);

    const lastPage = computed(() => pagination.value.last_page);

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Fetch courses with current filters and pagination
     */
    async function fetch(): Promise<void> {
        isLoading.value = true;

        // Build query params
        const queryParams: Record<string, unknown> = {
            ...filters.value,
            page: pagination.value.current_page,
        };

        // Remove undefined/null values
        Object.keys(queryParams).forEach(key => {
            if (queryParams[key] === undefined || queryParams[key] === null) {
                delete queryParams[key];
            }
        });

        await router.get(url, queryParams, {
            preserveState: true,
            preserveScroll: true,
            only,
            onSuccess: (page) => {
                const response = page.props.courses as {
                    data: (CourseListItem | Course)[];
                    meta?: PaginationMeta;
                    current_page?: number;
                    last_page?: number;
                    per_page?: number;
                    total?: number;
                };

                // Handle both paginated and non-paginated responses
                if (Array.isArray(response)) {
                    courses.value = response;
                } else if (response.data) {
                    courses.value = response.data;

                    // Update pagination from meta or direct properties
                    if (response.meta) {
                        pagination.value = response.meta;
                    } else if (response.current_page !== undefined) {
                        pagination.value = {
                            current_page: response.current_page,
                            last_page: response.last_page ?? 1,
                            per_page: response.per_page ?? 10,
                            total: response.total ?? response.data.length,
                            from: null,
                            to: null,
                        };
                    }
                }
            },
            onFinish: () => {
                isLoading.value = false;
            },
        });
    }

    /**
     * Debounced fetch for search
     */
    const debouncedFetch = debounce(fetch, DEBOUNCE.search);

    /**
     * Set filters and refetch (resets to page 1)
     */
    function setFilters(newFilters: Partial<CourseFilters>): void {
        filters.value = { ...filters.value, ...newFilters };
        pagination.value.current_page = 1;
        debouncedFetch();
    }

    /**
     * Clear all filters
     */
    function clearFilters(): void {
        filters.value = {};
        pagination.value.current_page = 1;
        fetch();
    }

    /**
     * Go to next page
     */
    function nextPage(): void {
        if (hasMore.value) {
            pagination.value.current_page++;
            fetch();
        }
    }

    /**
     * Go to previous page
     */
    function previousPage(): void {
        if (pagination.value.current_page > 1) {
            pagination.value.current_page--;
            fetch();
        }
    }

    /**
     * Go to specific page
     */
    function goToPage(page: number): void {
        if (page >= 1 && page <= pagination.value.last_page) {
            pagination.value.current_page = page;
            fetch();
        }
    }

    /**
     * Update courses (from Inertia props)
     */
    function setCourses(newCourses: (CourseListItem | Course)[], newMeta?: PaginationMeta): void {
        courses.value = newCourses;
        if (newMeta) {
            pagination.value = newMeta;
        }
    }

    /**
     * Search courses
     */
    function search(query: string): void {
        setFilters({ search: query || undefined });
    }

    return {
        // State
        courses,
        filters,
        pagination,
        isLoading,

        // Computed
        hasMore,
        isEmpty,
        totalCount,
        currentPage,
        lastPage,

        // Methods
        fetch,
        setFilters,
        clearFilters,
        nextPage,
        previousPage,
        goToPage,
        setCourses,
        search,
    };
}
