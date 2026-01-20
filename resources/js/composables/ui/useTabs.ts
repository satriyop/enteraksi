// =============================================================================
// useTabs Composable
// Tab navigation state management
// =============================================================================

import { ref, computed, watch, type Ref } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface UseTabsOptions<T extends string> {
    /** Initial active tab */
    initialTab?: T;
    /** Persist selection to URL hash */
    persistToHash?: boolean;
    /** Callback when tab changes */
    onChange?: (tab: T, previousTab: T) => void;
}

interface UseTabsReturn<T extends string> {
    /** Current active tab */
    currentTab: Ref<T>;
    /** Current tab index */
    currentIndex: Ref<number>;
    /** Whether current tab is first */
    isFirst: Ref<boolean>;
    /** Whether current tab is last */
    isLast: Ref<boolean>;
    /** Set active tab */
    setTab: (tab: T) => void;
    /** Go to next tab */
    next: () => void;
    /** Go to previous tab */
    previous: () => void;
    /** Go to tab by index */
    goToIndex: (index: number) => void;
    /** Check if a tab is active */
    isActive: (tab: T) => boolean;
    /** Get tab props for binding */
    getTabProps: (tab: T) => {
        'aria-selected': boolean;
        'aria-controls': string;
        tabindex: number;
        onClick: () => void;
    };
    /** Get panel props for binding */
    getPanelProps: (tab: T) => {
        id: string;
        role: string;
        hidden: boolean;
    };
}

// =============================================================================
// Composable
// =============================================================================

export function useTabs<T extends string>(
    tabs: readonly T[],
    options: UseTabsOptions<T> = {}
): UseTabsReturn<T> {
    const {
        initialTab,
        persistToHash = false,
        onChange,
    } = options;

    // =============================================================================
    // State
    // =============================================================================

    // Get initial tab from hash if persisting
    const getInitialTab = (): T => {
        if (persistToHash && typeof window !== 'undefined') {
            const hash = window.location.hash.slice(1);
            if (tabs.includes(hash as T)) {
                return hash as T;
            }
        }
        return initialTab ?? tabs[0];
    };

    const currentTab = ref<T>(getInitialTab()) as Ref<T>;

    // =============================================================================
    // Computed
    // =============================================================================

    const currentIndex = computed(() =>
        tabs.indexOf(currentTab.value)
    );

    const isFirst = computed(() =>
        currentIndex.value === 0
    );

    const isLast = computed(() =>
        currentIndex.value === tabs.length - 1
    );

    // =============================================================================
    // Methods
    // =============================================================================

    function setTab(tab: T): void {
        if (!tabs.includes(tab)) return;

        const previousTab = currentTab.value;
        if (previousTab === tab) return;

        currentTab.value = tab;

        // Update URL hash if persisting
        if (persistToHash && typeof window !== 'undefined') {
            window.history.replaceState(
                null,
                '',
                `${window.location.pathname}${window.location.search}#${tab}`
            );
        }

        // Trigger callback
        onChange?.(tab, previousTab);
    }

    function next(): void {
        if (!isLast.value) {
            setTab(tabs[currentIndex.value + 1]);
        }
    }

    function previous(): void {
        if (!isFirst.value) {
            setTab(tabs[currentIndex.value - 1]);
        }
    }

    function goToIndex(index: number): void {
        if (index >= 0 && index < tabs.length) {
            setTab(tabs[index]);
        }
    }

    function isActive(tab: T): boolean {
        return currentTab.value === tab;
    }

    /**
     * Get props for tab trigger element (for a11y)
     */
    function getTabProps(tab: T) {
        return {
            'aria-selected': isActive(tab),
            'aria-controls': `panel-${tab}`,
            tabindex: isActive(tab) ? 0 : -1,
            onClick: () => setTab(tab),
        };
    }

    /**
     * Get props for tab panel element (for a11y)
     */
    function getPanelProps(tab: T) {
        return {
            id: `panel-${tab}`,
            role: 'tabpanel',
            hidden: !isActive(tab),
        };
    }

    // =============================================================================
    // Hash Change Listener
    // =============================================================================

    if (persistToHash && typeof window !== 'undefined') {
        watch(() => {
            const handleHashChange = () => {
                const hash = window.location.hash.slice(1);
                if (tabs.includes(hash as T)) {
                    currentTab.value = hash as T;
                }
            };

            window.addEventListener('hashchange', handleHashChange);
            return () => window.removeEventListener('hashchange', handleHashChange);
        });
    }

    // =============================================================================
    // Return
    // =============================================================================

    return {
        currentTab,
        currentIndex,
        isFirst,
        isLast,
        setTab,
        next,
        previous,
        goToIndex,
        isActive,
        getTabProps,
        getPanelProps,
    };
}
