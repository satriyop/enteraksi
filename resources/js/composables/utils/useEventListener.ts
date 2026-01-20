// =============================================================================
// useEventListener Composable
// Automatically cleans up event listeners on component unmount
// =============================================================================

import { onMounted, onUnmounted, watch, unref, type Ref, isRef } from 'vue';

type Target = Window | Document | HTMLElement | Ref<HTMLElement | null | undefined>;

/**
 * Adds an event listener with automatic cleanup on unmount
 *
 * @example
 * // Window event
 * useEventListener(window, 'resize', handleResize);
 *
 * @example
 * // Ref element
 * const buttonRef = ref<HTMLButtonElement | null>(null);
 * useEventListener(buttonRef, 'click', handleClick);
 *
 * @example
 * // With options
 * useEventListener(window, 'scroll', handleScroll, { passive: true });
 */
export function useEventListener<K extends keyof WindowEventMap>(
    target: Window,
    event: K,
    handler: (event: WindowEventMap[K]) => void,
    options?: boolean | AddEventListenerOptions
): void;

export function useEventListener<K extends keyof DocumentEventMap>(
    target: Document,
    event: K,
    handler: (event: DocumentEventMap[K]) => void,
    options?: boolean | AddEventListenerOptions
): void;

export function useEventListener<K extends keyof HTMLElementEventMap>(
    target: HTMLElement | Ref<HTMLElement | null | undefined>,
    event: K,
    handler: (event: HTMLElementEventMap[K]) => void,
    options?: boolean | AddEventListenerOptions
): void;

export function useEventListener(
    target: Target,
    event: string,
    handler: EventListener,
    options?: boolean | AddEventListenerOptions
): void {
    function cleanup(el: EventTarget | null | undefined) {
        if (el) {
            el.removeEventListener(event, handler, options);
        }
    }

    function setup(el: EventTarget | null | undefined) {
        if (el) {
            el.addEventListener(event, handler, options);
        }
    }

    // Handle refs (reactive elements)
    if (isRef(target)) {
        watch(
            target,
            (newEl, oldEl) => {
                cleanup(oldEl);
                setup(newEl);
            },
            { immediate: true }
        );

        onUnmounted(() => cleanup(target.value));
    } else {
        // Handle static targets (window, document, or direct element)
        onMounted(() => setup(target as EventTarget));
        onUnmounted(() => cleanup(target as EventTarget));
    }
}
