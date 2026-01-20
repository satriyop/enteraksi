// =============================================================================
// Auth Store (Global Singleton)
// User authentication state management
// =============================================================================

import { ref, computed, readonly } from 'vue';
import type { User, UserRole } from '@/types';

// =============================================================================
// Singleton State (Module-level)
// =============================================================================

const user = ref<User | null>(null);
const isInitialized = ref(false);

// =============================================================================
// Computed
// =============================================================================

const isAuthenticated = computed(() => !!user.value);

const userRole = computed<UserRole | null>(() =>
    user.value?.role ?? null
);

const isLearner = computed(() =>
    user.value?.role === 'learner'
);

const isContentManager = computed(() =>
    user.value?.role === 'content_manager'
);

const isTrainer = computed(() =>
    user.value?.role === 'trainer'
);

const isAdmin = computed(() =>
    user.value?.role === 'lms_admin'
);

const canManageCourses = computed(() =>
    user.value?.role !== 'learner'
);

const canManageAssessments = computed(() =>
    ['content_manager', 'trainer', 'lms_admin'].includes(user.value?.role ?? '')
);

// =============================================================================
// Composable
// =============================================================================

export function useAuth() {
    /**
     * Initialize auth state from Inertia page props
     */
    function initialize(authUser: User | null): void {
        user.value = authUser;
        isInitialized.value = true;
    }

    /**
     * Set user (after login or profile update)
     */
    function setUser(newUser: User | null): void {
        user.value = newUser;
    }

    /**
     * Update user profile data
     */
    function updateProfile(data: Partial<User>): void {
        if (user.value) {
            user.value = { ...user.value, ...data };
        }
    }

    /**
     * Check if user has a specific role
     */
    function hasRole(role: UserRole): boolean {
        return user.value?.role === role;
    }

    /**
     * Check if user has any of the specified roles
     */
    function hasAnyRole(roles: UserRole[]): boolean {
        return roles.includes(user.value?.role as UserRole);
    }

    /**
     * Clear user on logout
     */
    function logout(): void {
        user.value = null;
    }

    /**
     * Get user ID safely
     */
    function getUserId(): number | null {
        return user.value?.id ?? null;
    }

    return {
        // State (readonly to prevent external mutation)
        user: readonly(user),
        isInitialized: readonly(isInitialized),

        // Computed
        isAuthenticated,
        userRole,
        isLearner,
        isContentManager,
        isTrainer,
        isAdmin,
        canManageCourses,
        canManageAssessments,

        // Methods
        initialize,
        setUser,
        updateProfile,
        hasRole,
        hasAnyRole,
        logout,
        getUserId,
    };
}
