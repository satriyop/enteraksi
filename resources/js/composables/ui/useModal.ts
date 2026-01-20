// =============================================================================
// useModal Composable
// Generic modal state management
// =============================================================================

import { ref, readonly, onUnmounted } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface UseModalOptions {
    /** Close modal when Escape key is pressed */
    closeOnEscape?: boolean;
    /** Close modal when clicking outside */
    closeOnClickOutside?: boolean;
}

interface UseModalReturn<T> {
    /** Whether the modal is currently open */
    isOpen: ReturnType<typeof readonly<typeof isOpen>>;
    /** Data passed to the modal */
    data: ReturnType<typeof readonly<typeof data>>;
    /** Open the modal with optional data */
    open: (modalData?: T) => void;
    /** Close the modal */
    close: () => void;
    /** Toggle the modal state */
    toggle: (modalData?: T) => void;
    /** Handler for click outside events */
    handleClickOutside: () => void;
}

// =============================================================================
// Composable
// =============================================================================

export function useModal<T = unknown>(options: UseModalOptions = {}): UseModalReturn<T> {
    const { closeOnEscape = true, closeOnClickOutside = true } = options;

    const isOpen = ref(false);
    const data = ref<T | null>(null);

    /**
     * Handle Escape key press
     */
    function handleEscape(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            close();
        }
    }

    /**
     * Open the modal with optional data
     */
    function open(modalData?: T): void {
        data.value = modalData ?? null;
        isOpen.value = true;

        if (closeOnEscape) {
            document.addEventListener('keydown', handleEscape);
        }
    }

    /**
     * Close the modal
     */
    function close(): void {
        isOpen.value = false;
        data.value = null;

        document.removeEventListener('keydown', handleEscape);
    }

    /**
     * Toggle the modal state
     */
    function toggle(modalData?: T): void {
        if (isOpen.value) {
            close();
        } else {
            open(modalData);
        }
    }

    /**
     * Handler for click outside events
     */
    function handleClickOutside(): void {
        if (closeOnClickOutside) {
            close();
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        document.removeEventListener('keydown', handleEscape);
    });

    return {
        isOpen: readonly(isOpen),
        data: readonly(data),
        open,
        close,
        toggle,
        handleClickOutside,
    };
}
