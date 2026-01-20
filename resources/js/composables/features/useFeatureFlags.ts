// =============================================================================
// useFeatureFlags Composable
// Manage feature flags for gradual rollouts
// =============================================================================

import { computed, readonly, ref } from 'vue';

// =============================================================================
// Types
// =============================================================================

/**
 * Available feature flags
 * Add new flags here as needed
 */
export interface FeatureFlags {
    /** New course creation form */
    newCourseForm: boolean;
    /** New lesson viewer with progress tracking */
    newLessonViewer: boolean;
    /** New assessment UI */
    newAssessmentUI: boolean;
    /** Virtual scrolling for long lists */
    virtualScrolling: boolean;
    /** Image lazy loading */
    lazyImages: boolean;
}

// =============================================================================
// Configuration
// =============================================================================

/**
 * Default feature flag values
 * Override via environment variables: VITE_FF_<FLAG_NAME>=true
 */
const defaultFlags: FeatureFlags = {
    newCourseForm: false,
    newLessonViewer: false,
    newAssessmentUI: false,
    virtualScrolling: true,
    lazyImages: true,
};

/**
 * Load flags from environment variables
 */
function loadFlags(): FeatureFlags {
    return {
        newCourseForm: import.meta.env.VITE_FF_NEW_COURSE_FORM === 'true',
        newLessonViewer: import.meta.env.VITE_FF_NEW_LESSON_VIEWER === 'true',
        newAssessmentUI: import.meta.env.VITE_FF_NEW_ASSESSMENT_UI === 'true',
        virtualScrolling: import.meta.env.VITE_FF_VIRTUAL_SCROLLING !== 'false',
        lazyImages: import.meta.env.VITE_FF_LAZY_IMAGES !== 'false',
    };
}

// =============================================================================
// State
// =============================================================================

const flags = ref<FeatureFlags>({
    ...defaultFlags,
    ...loadFlags(),
});

// =============================================================================
// Composable
// =============================================================================

/**
 * Feature flags for gradual rollouts
 *
 * @example
 * const { isEnabled } = useFeatureFlags();
 *
 * // Check if feature is enabled
 * if (isEnabled('newCourseForm')) {
 *     // Use new component
 * }
 *
 * @example
 * // In template
 * <NewForm v-if="isEnabled('newCourseForm')" />
 * <OldForm v-else />
 */
export function useFeatureFlags() {
    /**
     * Check if a feature flag is enabled
     */
    function isEnabled(flag: keyof FeatureFlags): boolean {
        return flags.value[flag];
    }

    /**
     * Get all flags as computed
     */
    const allFlags = computed(() => flags.value);

    /**
     * Override a flag at runtime (for testing)
     */
    function setFlag(flag: keyof FeatureFlags, value: boolean): void {
        flags.value[flag] = value;
    }

    /**
     * Reset all flags to defaults
     */
    function resetFlags(): void {
        flags.value = { ...defaultFlags, ...loadFlags() };
    }

    return {
        isEnabled,
        flags: readonly(allFlags),
        setFlag,
        resetFlags,
    };
}

// =============================================================================
// Percentage Rollout
// =============================================================================

/**
 * Simple hash function for consistent bucketing
 */
function simpleHash(str: string): number {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash);
}

/**
 * Check if user is in percentage rollout
 *
 * @example
 * // Enable for 10% of users
 * const showNewFeature = usePercentageRollout('newDashboard', 10, userId);
 */
export function usePercentageRollout(
    featureName: string,
    percentage: number,
    userId: number | string
): boolean {
    const hash = simpleHash(`${featureName}-${userId}`);
    const bucket = hash % 100;
    return bucket < percentage;
}
