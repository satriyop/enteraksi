/**
 * User-related type definitions
 *
 * Matches the actual User model and database schema.
 */

import type { Timestamps, UserId } from './common';

// =============================================================================
// User Role Enum
// =============================================================================

/**
 * User role - matches database ENUM('learner', 'content_manager', 'trainer', 'lms_admin')
 */
export const UserRole = {
    LEARNER: 'learner',
    CONTENT_MANAGER: 'content_manager',
    TRAINER: 'trainer',
    LMS_ADMIN: 'lms_admin',
} as const;
export type UserRole = (typeof UserRole)[keyof typeof UserRole];

// =============================================================================
// User Types
// =============================================================================

/**
 * Base User model - matches database columns and commonly serialized fields.
 *
 * Note: password, remember_token, two_factor_* are hidden in Laravel and never
 * serialized to the frontend.
 */
export interface User extends Timestamps {
    id: UserId;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: UserRole;

    // Optional: only present when explicitly loaded or computed
    avatar?: string;

    // Computed properties from model accessors (rarely serialized)
    is_learner?: boolean;
    is_content_manager?: boolean;
    is_trainer?: boolean;
    is_lms_admin?: boolean;
    can_manage_courses?: boolean;
}

/**
 * Minimal user representation for lists and references.
 * Use this when you only need id/name/avatar (e.g., instructor display).
 */
export interface UserSummary {
    id: UserId;
    name: string;
    avatar?: string;
}

/**
 * User with full details - used in admin contexts.
 */
export interface UserWithDetails extends User {
    courses_count?: number;
    enrollments_count?: number;
}

// =============================================================================
// Form Data Types (for creating/updating)
// =============================================================================

/**
 * Data required to create a new user.
 */
export interface CreateUserData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role?: UserRole;
}

/**
 * Data for updating an existing user.
 * All fields optional since PATCH allows partial updates.
 */
export interface UpdateUserData {
    name?: string;
    email?: string;
    password?: string;
    password_confirmation?: string;
    role?: UserRole;
}

/**
 * Data for updating profile (without role - users can't change their own role).
 */
export interface UpdateProfileData {
    name?: string;
    email?: string;
}

/**
 * Data for changing password.
 */
export interface ChangePasswordData {
    current_password: string;
    password: string;
    password_confirmation: string;
}

// =============================================================================
// Filter/Query Types
// =============================================================================

/**
 * Query parameters for filtering users list.
 */
export interface UserFilters {
    search?: string;
    role?: UserRole;
    email_verified?: boolean;
    sort_by?: 'name' | 'email' | 'created_at' | 'role';
    sort_order?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
}

// =============================================================================
// Type Guards
// =============================================================================

/**
 * Check if user has a specific role.
 */
export function hasRole(user: User, role: UserRole): boolean {
    return user.role === role;
}

/**
 * Check if user can manage courses (content_manager, trainer, or lms_admin).
 */
export function canManageCourses(user: User): boolean {
    return ['content_manager', 'trainer', 'lms_admin'].includes(user.role);
}

/**
 * Check if user is admin (lms_admin).
 */
export function isAdmin(user: User): boolean {
    return user.role === 'lms_admin';
}
