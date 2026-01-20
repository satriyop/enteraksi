// =============================================================================
// Inertia Test Helpers
// Utilities for testing Inertia.js components
// =============================================================================

import { vi } from 'vitest';
import { config } from '@vue/test-utils';
import type { User } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface MockPageProps {
    auth: {
        user: User | null;
    };
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    [key: string]: unknown;
}

// =============================================================================
// Create Mock Page Props
// =============================================================================

/**
 * Create mock page props for testing Inertia pages
 */
export function createMockPage<T extends Record<string, unknown>>(
    props: T,
    options: {
        user?: Partial<User> | null;
        flash?: MockPageProps['flash'];
    } = {}
): { props: T & MockPageProps } {
    const defaultUser: User = {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        email_verified_at: new Date().toISOString(),
        role: 'learner',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    };

    return {
        props: {
            auth: {
                user: options.user === null ? null : { ...defaultUser, ...options.user },
            },
            flash: options.flash ?? {},
            ...props,
        } as T & MockPageProps,
    };
}

// =============================================================================
// Mock Inertia Router
// =============================================================================

/**
 * Create a mock Inertia router with tracked calls
 */
export function mockInertiaRouter() {
    return {
        visit: vi.fn(),
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        reload: vi.fn(),
        on: vi.fn(),
    };
}

// =============================================================================
// Setup Inertia Stubs
// =============================================================================

/**
 * Configure Vue Test Utils with Inertia component stubs
 */
export function setupInertiaStubs(): void {
    config.global.stubs = {
        Link: {
            template: '<a :href="href"><slot /></a>',
            props: ['href', 'method', 'as', 'data', 'preserveScroll', 'preserveState', 'replace', 'only'],
        },
        Head: {
            template: '<div />',
            props: ['title'],
        },
    };
}

// =============================================================================
// Mock usePage
// =============================================================================

/**
 * Create a mock usePage composable
 */
export function createMockUsePage<T extends Record<string, unknown>>(
    props: T,
    options: {
        user?: Partial<User> | null;
        flash?: MockPageProps['flash'];
    } = {}
) {
    const page = createMockPage(props, options);

    return vi.fn(() => ({
        props: page.props,
        url: '/',
        component: 'TestComponent',
        version: '1.0.0',
    }));
}
