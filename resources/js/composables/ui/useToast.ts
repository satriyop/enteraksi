// =============================================================================
// useToast Composable
// Global toast notification state management
// =============================================================================

import { ref, readonly } from 'vue';
import { TOAST_DURATION } from '@/lib/constants';

// =============================================================================
// Types
// =============================================================================

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface Toast {
    id: number;
    type: ToastType;
    title: string;
    message?: string;
    duration: number;
}

interface ToastOptions {
    /** Toast title */
    title: string;
    /** Optional detailed message */
    message?: string;
    /** Duration in ms (0 for persistent) */
    duration?: number;
}

// =============================================================================
// Global State (Singleton)
// =============================================================================

let toastIdCounter = 0;
const toasts = ref<Toast[]>([]);

// =============================================================================
// Composable
// =============================================================================

export function useToast() {
    /**
     * Add a toast notification
     */
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

        // Auto-dismiss after duration (if not persistent)
        if (duration > 0) {
            setTimeout(() => dismiss(id), duration);
        }

        return id;
    }

    /**
     * Dismiss a toast by ID
     */
    function dismiss(id: number): void {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index > -1) {
            toasts.value.splice(index, 1);
        }
    }

    /**
     * Clear all toasts
     */
    function clear(): void {
        toasts.value = [];
    }

    // =============================================================================
    // Convenience Methods
    // =============================================================================

    /**
     * Show a success toast
     */
    function success(options: ToastOptions): number {
        return add('success', options);
    }

    /**
     * Show an error toast (with longer duration by default)
     */
    function error(options: ToastOptions): number {
        return add('error', {
            ...options,
            duration: options.duration ?? TOAST_DURATION.long,
        });
    }

    /**
     * Show a warning toast
     */
    function warning(options: ToastOptions): number {
        return add('warning', options);
    }

    /**
     * Show an info toast
     */
    function info(options: ToastOptions): number {
        return add('info', options);
    }

    return {
        // State
        toasts: readonly(toasts),

        // Core methods
        add,
        dismiss,
        clear,

        // Convenience methods
        success,
        error,
        warning,
        info,
    };
}
