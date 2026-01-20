/**
 * Enteraksi LMS Type Definitions
 *
 * This file exports all type definitions used across the application.
 * Import types from this file for consistent type usage.
 *
 * @example
 * // Import specific types
 * import type { Course, Lesson, User } from '@/types';
 *
 * // Import constants for iteration
 * import { CourseStatus, ContentType } from '@/types';
 *
 * // Import type guards
 * import { isPublished, isVideoLesson } from '@/types';
 */

// =============================================================================
// Re-export all types from model files
// =============================================================================

// Common types (pagination, timestamps, statuses, IDs)
export * from './models/common';

// Domain model types
export * from './models/user';
export * from './models/course';
export * from './models/lesson';
export * from './models/assessment';
export * from './models/enrollment';

// API/Inertia response types
export * from './api/responses';

// =============================================================================
// Inertia-specific types (kept from original for compatibility)
// =============================================================================

import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';
import type { User } from './models/user';

/**
 * Auth shape passed to all pages via Inertia.
 */
export interface Auth {
    user: User;
}

/**
 * Breadcrumb item for navigation.
 */
export interface BreadcrumbItem {
    title: string;
    href: string;
}

/**
 * Navigation item for sidebar/menu.
 */
export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    badge?: string | number;
    children?: NavItem[];
}

/**
 * App-wide page props passed by Inertia.
 * Use this to type your page components.
 *
 * @example
 * const props = defineProps<AppPageProps<{
 *     course: Course;
 *     categories: Category[];
 * }>>();
 */
export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
};

/**
 * @deprecated Use BreadcrumbItem instead
 */
export type BreadcrumbItemType = BreadcrumbItem;
