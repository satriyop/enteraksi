// =============================================================================
// useOptimisticUpdate Composable
// Optimistic updates with automatic rollback on error
// =============================================================================

import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

// =============================================================================
// Types
// =============================================================================

type HttpMethod = 'post' | 'put' | 'patch' | 'delete';

interface UseOptimisticUpdateOptions<T> {
    /** Callback when update succeeds */
    onSuccess?: () => void;
    /** Callback when update fails */
    onError?: (error: unknown) => void;
    /** Callback for rollback (if needed beyond automatic restore) */
    onRollback?: () => void;
}

interface OptimisticUpdateConfig<T> {
    /** Current data ref to update */
    data: Ref<T>;
    /** Function that returns the optimistically updated data */
    optimisticUpdate: (current: T) => T;
    /** Server endpoint to call */
    endpoint: string;
    /** HTTP method (default: 'post') */
    method?: HttpMethod;
    /** Request payload */
    payload?: Record<string, unknown>;
}

interface UseOptimisticUpdateReturn<T> {
    /** Whether an update is in progress */
    isPending: Ref<boolean>;
    /** Error message if update failed */
    error: Ref<string | null>;
    /** Execute an optimistic update */
    execute: (config: OptimisticUpdateConfig<T>) => Promise<boolean>;
    /** Clear error state */
    clearError: () => void;
}

// =============================================================================
// Composable
// =============================================================================

export function useOptimisticUpdate<T = unknown>(
    options: UseOptimisticUpdateOptions<T> = {}
): UseOptimisticUpdateReturn<T> {
    const { onSuccess, onError, onRollback } = options;

    // =============================================================================
    // State
    // =============================================================================

    const isPending = ref(false);
    const error = ref<string | null>(null);

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Execute an optimistic update
     *
     * 1. Store original data for potential rollback
     * 2. Apply optimistic update immediately
     * 3. Send request to server
     * 4. Rollback if request fails
     */
    async function execute(config: OptimisticUpdateConfig<T>): Promise<boolean> {
        const {
            data,
            optimisticUpdate,
            endpoint,
            method = 'post',
            payload = {},
        } = config;

        // Store original for rollback
        const originalData = JSON.parse(JSON.stringify(data.value)) as T;

        // Apply optimistic update immediately
        try {
            data.value = optimisticUpdate(data.value);
        } catch (err) {
            console.error('Optimistic update function failed:', err);
            error.value = 'Gagal memperbarui data';
            return false;
        }

        isPending.value = true;
        error.value = null;

        return new Promise((resolve) => {
            router[method](endpoint, payload, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    isPending.value = false;
                    onSuccess?.();
                    resolve(true);
                },
                onError: (errors) => {
                    // Rollback on error
                    data.value = originalData;
                    isPending.value = false;

                    // Extract error message
                    if (typeof errors === 'object' && errors !== null) {
                        const firstError = Object.values(errors)[0];
                        error.value = typeof firstError === 'string'
                            ? firstError
                            : 'Terjadi kesalahan';
                    } else {
                        error.value = 'Terjadi kesalahan';
                    }

                    onError?.(errors);
                    onRollback?.();
                    resolve(false);
                },
            });
        });
    }

    /**
     * Clear error state
     */
    function clearError(): void {
        error.value = null;
    }

    // =============================================================================
    // Return
    // =============================================================================

    return {
        isPending,
        error,
        execute,
        clearError,
    };
}

// =============================================================================
// Simplified Helper
// =============================================================================

/**
 * One-shot optimistic update (for simple cases)
 *
 * @example
 * ```ts
 * const todos = ref([...]);
 *
 * async function toggleTodo(id: number) {
 *     await optimisticUpdate({
 *         data: todos,
 *         optimisticUpdate: (current) =>
 *             current.map(t => t.id === id ? { ...t, done: !t.done } : t),
 *         endpoint: `/todos/${id}/toggle`,
 *         method: 'patch',
 *     });
 * }
 * ```
 */
export async function optimisticUpdate<T>(
    config: OptimisticUpdateConfig<T>
): Promise<boolean> {
    const { execute } = useOptimisticUpdate<T>();
    return execute(config);
}
