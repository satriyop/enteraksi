// =============================================================================
// Test Setup
// Global configuration for Vitest tests
// =============================================================================

import { config } from '@vue/test-utils';
import { vi } from 'vitest';

// =============================================================================
// Global Stubs for Inertia Components
// =============================================================================

config.global.stubs = {
    // Stub Inertia Link component
    Link: {
        template: '<a :href="href"><slot /></a>',
        props: ['href', 'method', 'as', 'data', 'preserveScroll', 'preserveState', 'replace', 'only'],
    },
    // Stub Inertia Head component
    Head: {
        template: '<div />',
        props: ['title'],
    },
};

// =============================================================================
// Mock Inertia Router
// =============================================================================

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual('@inertiajs/vue3');
    return {
        ...actual,
        router: {
            visit: vi.fn(),
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            reload: vi.fn(),
            on: vi.fn(),
        },
        usePage: vi.fn(() => ({
            props: {
                auth: {
                    user: {
                        id: 1,
                        name: 'Test User',
                        email: 'test@example.com',
                        role: 'learner',
                    },
                },
                flash: {},
            },
        })),
        useForm: vi.fn((initialData) => {
            const data = { ...initialData };
            return {
                ...data,
                data: () => data,
                isDirty: false,
                errors: {},
                hasErrors: false,
                processing: false,
                progress: null,
                wasSuccessful: false,
                recentlySuccessful: false,
                transform: vi.fn().mockReturnThis(),
                defaults: vi.fn().mockReturnThis(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
                setError: vi.fn(),
                submit: vi.fn(),
                get: vi.fn(),
                post: vi.fn(),
                put: vi.fn(),
                patch: vi.fn(),
                delete: vi.fn(),
            };
        }),
    };
});

// =============================================================================
// Mock window.matchMedia
// =============================================================================

Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});

// =============================================================================
// Mock localStorage
// =============================================================================

const localStorageMock = (() => {
    let store: Record<string, string> = {};
    return {
        getItem: vi.fn((key: string) => store[key] ?? null),
        setItem: vi.fn((key: string, value: string) => {
            store[key] = value;
        }),
        removeItem: vi.fn((key: string) => {
            delete store[key];
        }),
        clear: vi.fn(() => {
            store = {};
        }),
        get length() {
            return Object.keys(store).length;
        },
        key: vi.fn((index: number) => Object.keys(store)[index] ?? null),
    };
})();

Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// =============================================================================
// Mock sessionStorage
// =============================================================================

const sessionStorageMock = (() => {
    let store: Record<string, string> = {};
    return {
        getItem: vi.fn((key: string) => store[key] ?? null),
        setItem: vi.fn((key: string, value: string) => {
            store[key] = value;
        }),
        removeItem: vi.fn((key: string) => {
            delete store[key];
        }),
        clear: vi.fn(() => {
            store = {};
        }),
        get length() {
            return Object.keys(store).length;
        },
        key: vi.fn((index: number) => Object.keys(store)[index] ?? null),
    };
})();

Object.defineProperty(window, 'sessionStorage', { value: sessionStorageMock });

// =============================================================================
// Mock ResizeObserver
// =============================================================================

class ResizeObserverMock {
    observe = vi.fn();
    unobserve = vi.fn();
    disconnect = vi.fn();
}

Object.defineProperty(window, 'ResizeObserver', {
    writable: true,
    value: ResizeObserverMock,
});

// =============================================================================
// Mock IntersectionObserver
// =============================================================================

class IntersectionObserverMock {
    observe = vi.fn();
    unobserve = vi.fn();
    disconnect = vi.fn();
}

Object.defineProperty(window, 'IntersectionObserver', {
    writable: true,
    value: IntersectionObserverMock,
});

// =============================================================================
// Mock window.scrollTo
// =============================================================================

Object.defineProperty(window, 'scrollTo', {
    writable: true,
    value: vi.fn(),
});

// =============================================================================
// Mock URL.createObjectURL
// =============================================================================

Object.defineProperty(URL, 'createObjectURL', {
    writable: true,
    value: vi.fn(() => 'blob:mock-url'),
});

Object.defineProperty(URL, 'revokeObjectURL', {
    writable: true,
    value: vi.fn(),
});

// =============================================================================
// Reset Mocks Between Tests
// =============================================================================

beforeEach(() => {
    vi.clearAllMocks();
    localStorageMock.clear();
    sessionStorageMock.clear();
});

// =============================================================================
// Console Suppression (optional - uncomment to suppress console noise in tests)
// =============================================================================

// vi.spyOn(console, 'warn').mockImplementation(() => {});
// vi.spyOn(console, 'error').mockImplementation(() => {});
