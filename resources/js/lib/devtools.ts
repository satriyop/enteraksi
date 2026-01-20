// =============================================================================
// DevTools
// Development-only debugging utilities for state management
// =============================================================================

import { ref, watch, type Ref, type ComputedRef } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface StoreRegistration {
    name: string;
    state: Ref<unknown> | ComputedRef<unknown>;
    registeredAt: Date;
}

interface StateChange {
    storeName: string;
    timestamp: Date;
    previousValue: unknown;
    newValue: unknown;
}

// =============================================================================
// Store Registry
// =============================================================================

const stores = new Map<string, StoreRegistration>();
const stateHistory: StateChange[] = [];
const MAX_HISTORY_SIZE = 100;

/**
 * Register a store for debugging
 *
 * @param name - Unique name for the store
 * @param state - The reactive state to track
 */
export function registerStore(
    name: string,
    state: Ref<unknown> | ComputedRef<unknown>
): void {
    if (!import.meta.env.DEV) return;

    stores.set(name, {
        name,
        state,
        registeredAt: new Date(),
    });

    // Watch for changes and log them
    watch(
        state,
        (newValue, oldValue) => {
            const change: StateChange = {
                storeName: name,
                timestamp: new Date(),
                previousValue: JSON.parse(JSON.stringify(oldValue)),
                newValue: JSON.parse(JSON.stringify(newValue)),
            };

            // Add to history
            stateHistory.unshift(change);
            if (stateHistory.length > MAX_HISTORY_SIZE) {
                stateHistory.pop();
            }

            // Log the change
            console.groupCollapsed(
                `%c🔄 Store Update: ${name}`,
                'color: #4a9eff; font-weight: bold;'
            );
            console.log('%cPrevious:', 'color: #ff6b6b;', oldValue);
            console.log('%cNew:', 'color: #51cf66;', newValue);
            console.trace('Stack trace');
            console.groupEnd();
        },
        { deep: true }
    );

    console.log(
        `%c📦 Store Registered: ${name}`,
        'color: #51cf66; font-weight: bold;'
    );
}

/**
 * Unregister a store
 */
export function unregisterStore(name: string): void {
    if (!import.meta.env.DEV) return;
    stores.delete(name);
}

/**
 * Get all registered stores
 */
export function getStores(): Map<string, StoreRegistration> {
    return stores;
}

/**
 * Get current state of all stores
 */
export function getStoreStates(): Record<string, unknown> {
    const states: Record<string, unknown> = {};

    for (const [name, registration] of stores) {
        states[name] = registration.state.value;
    }

    return states;
}

/**
 * Get state change history
 */
export function getStateHistory(): readonly StateChange[] {
    return stateHistory;
}

/**
 * Clear state history
 */
export function clearStateHistory(): void {
    stateHistory.length = 0;
}

// =============================================================================
// Debug Utilities
// =============================================================================

/**
 * Log current state of a specific store
 */
export function inspectStore(name: string): void {
    if (!import.meta.env.DEV) return;

    const store = stores.get(name);
    if (!store) {
        console.warn(`Store "${name}" not found`);
        return;
    }

    console.group(`%c📋 Store Inspection: ${name}`, 'color: #4a9eff; font-weight: bold;');
    console.log('Current Value:', store.state.value);
    console.log('Registered At:', store.registeredAt);
    console.groupEnd();
}

/**
 * Log all stores
 */
export function inspectAllStores(): void {
    if (!import.meta.env.DEV) return;

    console.group('%c📋 All Stores', 'color: #4a9eff; font-weight: bold;');

    if (stores.size === 0) {
        console.log('No stores registered');
    } else {
        for (const [name, registration] of stores) {
            console.group(name);
            console.log('Value:', registration.state.value);
            console.log('Registered:', registration.registeredAt);
            console.groupEnd();
        }
    }

    console.groupEnd();
}

// =============================================================================
// Performance Profiling
// =============================================================================

const renderTimings = new Map<string, number[]>();

/**
 * Start timing a component render
 */
export function startRenderProfile(componentName: string): () => void {
    if (!import.meta.env.DEV) return () => {};

    const startTime = performance.now();

    return () => {
        const duration = performance.now() - startTime;

        let timings = renderTimings.get(componentName);
        if (!timings) {
            timings = [];
            renderTimings.set(componentName, timings);
        }

        timings.push(duration);

        // Keep only last 100 timings
        if (timings.length > 100) {
            timings.shift();
        }

        if (duration > 16) {
            // Longer than one frame
            console.warn(
                `%c⚠️ Slow render: ${componentName} took ${duration.toFixed(2)}ms`,
                'color: #ffa94d;'
            );
        }
    };
}

/**
 * Get render statistics for a component
 */
export function getRenderStats(componentName: string): {
    count: number;
    average: number;
    min: number;
    max: number;
} | null {
    const timings = renderTimings.get(componentName);
    if (!timings || timings.length === 0) return null;

    return {
        count: timings.length,
        average: timings.reduce((a, b) => a + b, 0) / timings.length,
        min: Math.min(...timings),
        max: Math.max(...timings),
    };
}

// =============================================================================
// Window Exposure (Development Only)
// =============================================================================

if (import.meta.env.DEV && typeof window !== 'undefined') {
    const devTools = {
        stores,
        getStoreStates,
        getStateHistory,
        clearStateHistory,
        inspectStore,
        inspectAllStores,
        renderTimings,
        getRenderStats,
    };

    (window as unknown as { __ENTERAKSI_DEVTOOLS__: typeof devTools }).__ENTERAKSI_DEVTOOLS__ = devTools;

    console.log(
        '%c🛠️ Enteraksi DevTools available at window.__ENTERAKSI_DEVTOOLS__',
        'color: #51cf66; font-weight: bold;'
    );
}

// =============================================================================
// Export
// =============================================================================

export const devtools = {
    registerStore,
    unregisterStore,
    getStores,
    getStoreStates,
    getStateHistory,
    clearStateHistory,
    inspectStore,
    inspectAllStores,
    startRenderProfile,
    getRenderStats,
};
