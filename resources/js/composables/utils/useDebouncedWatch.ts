// =============================================================================
// useDebouncedWatch Composable
// Watch with automatic debouncing
// =============================================================================

import {
    watch,
    type WatchSource,
    type WatchCallback,
    type WatchOptions,
    type WatchStopHandle,
} from 'vue';

/**
 * Watch a source with debounced callback
 *
 * @example
 * // Debounce search input
 * const search = ref('');
 * useDebouncedWatch(search, (value) => {
 *     fetchResults(value);
 * }, 300);
 *
 * @example
 * // With watch options
 * useDebouncedWatch(
 *     () => props.query,
 *     (newQuery) => performSearch(newQuery),
 *     500,
 *     { immediate: true }
 * );
 */
export function useDebouncedWatch<T>(
    source: WatchSource<T>,
    callback: WatchCallback<T, T | undefined>,
    delay: number = 300,
    options?: WatchOptions
): WatchStopHandle {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const debouncedCallback: WatchCallback<T, T | undefined> = (
        newValue,
        oldValue,
        onCleanup
    ) => {
        // Clear previous timeout
        if (timeoutId !== null) {
            clearTimeout(timeoutId);
        }

        // Set cleanup function
        onCleanup(() => {
            if (timeoutId !== null) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
        });

        // Schedule callback
        timeoutId = setTimeout(() => {
            callback(newValue, oldValue, onCleanup);
            timeoutId = null;
        }, delay);
    };

    return watch(source, debouncedCallback, options);
}

/**
 * Watch multiple sources with debounced callback
 *
 * @example
 * useDebouncedWatchMultiple(
 *     [search, filters],
 *     ([searchVal, filtersVal]) => {
 *         fetchData(searchVal, filtersVal);
 *     },
 *     300
 * );
 */
export function useDebouncedWatchMultiple<T extends readonly WatchSource<unknown>[]>(
    sources: T,
    callback: WatchCallback<
        { [K in keyof T]: T[K] extends WatchSource<infer V> ? V : never },
        { [K in keyof T]: T[K] extends WatchSource<infer V> ? V : never } | undefined
    >,
    delay: number = 300,
    options?: WatchOptions
): WatchStopHandle {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const debouncedCallback = (
        newValues: unknown,
        oldValues: unknown,
        onCleanup: (fn: () => void) => void
    ) => {
        if (timeoutId !== null) {
            clearTimeout(timeoutId);
        }

        onCleanup(() => {
            if (timeoutId !== null) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
        });

        timeoutId = setTimeout(() => {
            (callback as (n: unknown, o: unknown, c: (fn: () => void) => void) => void)(
                newValues,
                oldValues,
                onCleanup
            );
            timeoutId = null;
        }, delay);
    };

    return watch(sources, debouncedCallback, options);
}
