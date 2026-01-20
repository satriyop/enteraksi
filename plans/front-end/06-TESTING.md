# Phase 6: Testing Strategy

## Overview

This phase establishes a comprehensive testing strategy for the Vue.js frontend. Currently, there are 0% frontend tests, creating significant regression risk. This plan covers unit tests, component tests, and integration tests using Vitest and Vue Test Utils.

**Duration:** 2-3 weeks
**Risk Level:** Low
**Dependencies:** Phase 3 (Components), Phase 4 (Composables)

---

## Testing Stack

| Tool | Purpose | Version |
|------|---------|---------|
| Vitest | Test runner | ^2.0 |
| @vue/test-utils | Vue component testing | ^2.4 |
| @testing-library/vue | User-centric testing | ^8.0 |
| happy-dom | DOM implementation | ^15.0 |
| msw | API mocking | ^2.0 |

---

## Test Categories

### 1. Unit Tests
- Utility functions
- Composables
- Type guards
- Pure functions

### 2. Component Tests
- Isolated component behavior
- Props, emits, slots
- User interactions

### 3. Integration Tests
- Multiple components working together
- Page-level testing
- Inertia integration

---

## Setup

### Install Dependencies

```bash
npm install -D vitest @vue/test-utils @testing-library/vue happy-dom msw @vitest/coverage-v8
```

### Configure Vitest

**File: `vitest.config.ts`**
```typescript
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        globals: true,
        environment: 'happy-dom',
        include: ['resources/js/**/*.{test,spec}.{js,ts}'],
        setupFiles: ['./resources/js/tests/setup.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html', 'lcov'],
            include: [
                'resources/js/components/**/*.vue',
                'resources/js/composables/**/*.ts',
                'resources/js/lib/**/*.ts',
            ],
            exclude: [
                'resources/js/components/ui/**', // shadcn-vue components
                '**/*.d.ts',
                '**/*.test.ts',
            ],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, './resources/js'),
        },
    },
});
```

### Test Setup File

**File: `resources/js/tests/setup.ts`**
```typescript
import { config } from '@vue/test-utils';
import { vi } from 'vitest';

// Global stubs for common components
config.global.stubs = {
    // Stub Inertia components
    Link: {
        template: '<a><slot /></a>',
        props: ['href'],
    },
    Head: {
        template: '<div />',
        props: ['title'],
    },
};

// Mock Inertia router
vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual('@inertiajs/vue3');
    return {
        ...actual,
        router: {
            visit: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            reload: vi.fn(),
        },
        usePage: vi.fn(() => ({
            props: {
                auth: { user: { id: 1, name: 'Test User' } },
                flash: {},
            },
        })),
    };
});

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation(query => ({
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

// Mock localStorage
const localStorageMock = {
    getItem: vi.fn(),
    setItem: vi.fn(),
    clear: vi.fn(),
    removeItem: vi.fn(),
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// Reset mocks between tests
beforeEach(() => {
    vi.clearAllMocks();
});
```

---

## Unit Testing

### Testing Utilities

**File: `resources/js/lib/__tests__/formatters.test.ts`**
```typescript
import { describe, it, expect } from 'vitest';
import {
    formatDuration,
    formatCurrency,
    formatPercentage,
    difficultyLabel,
    courseStatusLabel,
} from '../formatters';

describe('formatDuration', () => {
    it('returns "-" for null/undefined', () => {
        expect(formatDuration(null)).toBe('-');
        expect(formatDuration(undefined)).toBe('-');
        expect(formatDuration(0)).toBe('-');
    });

    it('formats minutes only', () => {
        expect(formatDuration(30)).toBe('30m');
        expect(formatDuration(45)).toBe('45m');
    });

    it('formats hours only', () => {
        expect(formatDuration(60)).toBe('1h');
        expect(formatDuration(120)).toBe('2h');
    });

    it('formats hours and minutes', () => {
        expect(formatDuration(90)).toBe('1h 30m');
        expect(formatDuration(150)).toBe('2h 30m');
    });

    it('supports long format', () => {
        expect(formatDuration(90, 'long')).toBe('1 jam 30 menit');
        expect(formatDuration(60, 'long')).toBe('1 jam');
        expect(formatDuration(30, 'long')).toBe('30 menit');
    });

    it('supports compact format', () => {
        expect(formatDuration(90, 'compact')).toBe('1.5h');
        expect(formatDuration(30, 'compact')).toBe('30m');
    });
});

describe('formatCurrency', () => {
    it('returns "-" for null/undefined', () => {
        expect(formatCurrency(null)).toBe('-');
        expect(formatCurrency(undefined)).toBe('-');
    });

    it('returns "Gratis" for zero', () => {
        expect(formatCurrency(0)).toBe('Gratis');
    });

    it('does not show free label when option is false', () => {
        expect(formatCurrency(0, { showFree: false })).toMatch(/Rp.*0/);
    });

    it('formats currency in IDR', () => {
        expect(formatCurrency(100000)).toMatch(/Rp.*100\.000/);
        expect(formatCurrency(1500000)).toMatch(/Rp.*1\.500\.000/);
    });

    it('supports compact format', () => {
        expect(formatCurrency(1500000, { compact: true })).toBe('Rp 1.5jt');
        expect(formatCurrency(50000, { compact: true })).toBe('Rp 50rb');
    });
});

describe('difficultyLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(difficultyLabel('beginner')).toBe('Pemula');
        expect(difficultyLabel('intermediate')).toBe('Menengah');
        expect(difficultyLabel('advanced')).toBe('Lanjutan');
    });

    it('returns "-" for null/undefined', () => {
        expect(difficultyLabel(null)).toBe('-');
        expect(difficultyLabel(undefined)).toBe('-');
    });
});

describe('courseStatusLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(courseStatusLabel('draft')).toBe('Draf');
        expect(courseStatusLabel('published')).toBe('Dipublikasikan');
        expect(courseStatusLabel('archived')).toBe('Diarsipkan');
    });
});
```

**File: `resources/js/lib/__tests__/string.test.ts`**
```typescript
import { describe, it, expect } from 'vitest';
import {
    truncate,
    truncateWords,
    slugify,
    getInitials,
    isValidEmail,
} from '../string';

describe('truncate', () => {
    it('returns empty string for null/undefined', () => {
        expect(truncate(null, 10)).toBe('');
        expect(truncate(undefined, 10)).toBe('');
    });

    it('returns original if shorter than limit', () => {
        expect(truncate('Hello', 10)).toBe('Hello');
    });

    it('truncates with ellipsis', () => {
        expect(truncate('Hello World', 8)).toBe('Hello...');
    });

    it('supports custom suffix', () => {
        expect(truncate('Hello World', 8, '…')).toBe('Hello W…');
    });
});

describe('truncateWords', () => {
    it('returns original if fewer words than limit', () => {
        expect(truncateWords('Hello World', 5)).toBe('Hello World');
    });

    it('truncates by word count', () => {
        expect(truncateWords('One two three four five', 3)).toBe('One two three...');
    });
});

describe('slugify', () => {
    it('converts to lowercase kebab-case', () => {
        expect(slugify('Hello World')).toBe('hello-world');
        expect(slugify('  Multiple   Spaces  ')).toBe('multiple-spaces');
    });

    it('removes special characters', () => {
        expect(slugify('Hello! @World#')).toBe('hello-world');
    });
});

describe('getInitials', () => {
    it('returns initials from name', () => {
        expect(getInitials('John Doe')).toBe('JD');
        expect(getInitials('Alice Bob Charlie')).toBe('AB');
    });

    it('respects maxLength', () => {
        expect(getInitials('Alice Bob Charlie', 3)).toBe('ABC');
        expect(getInitials('John', 2)).toBe('J');
    });

    it('handles empty input', () => {
        expect(getInitials(null)).toBe('');
        expect(getInitials('')).toBe('');
    });
});

describe('isValidEmail', () => {
    it('validates correct emails', () => {
        expect(isValidEmail('test@example.com')).toBe(true);
        expect(isValidEmail('user.name@domain.co.id')).toBe(true);
    });

    it('rejects invalid emails', () => {
        expect(isValidEmail('invalid')).toBe(false);
        expect(isValidEmail('missing@domain')).toBe(false);
        expect(isValidEmail('@nodomain.com')).toBe(false);
    });
});
```

### Testing Composables

**File: `resources/js/composables/__tests__/useModal.test.ts`**
```typescript
import { describe, it, expect, vi } from 'vitest';
import { useModal, useConfirmation } from '../ui/useModal';

describe('useModal', () => {
    it('starts closed', () => {
        const { isOpen } = useModal();
        expect(isOpen.value).toBe(false);
    });

    it('opens and closes', () => {
        const { isOpen, open, close } = useModal();

        open();
        expect(isOpen.value).toBe(true);

        close();
        expect(isOpen.value).toBe(false);
    });

    it('stores data when opened', () => {
        const { data, open, close } = useModal<{ id: number }>();

        open({ id: 123 });
        expect(data.value).toEqual({ id: 123 });

        close();
        expect(data.value).toBeNull();
    });

    it('toggles state', () => {
        const { isOpen, toggle } = useModal();

        toggle();
        expect(isOpen.value).toBe(true);

        toggle();
        expect(isOpen.value).toBe(false);
    });

    it('adds escape key listener when opened', () => {
        const addEventListenerSpy = vi.spyOn(document, 'addEventListener');
        const { open, close } = useModal();

        open();
        expect(addEventListenerSpy).toHaveBeenCalledWith('keydown', expect.any(Function));

        close();
    });
});

describe('useConfirmation', () => {
    it('resolves true when confirmed', async () => {
        const { confirm, handleConfirm } = useConfirmation();

        const promise = confirm({ message: 'Are you sure?' });
        handleConfirm();

        await expect(promise).resolves.toBe(true);
    });

    it('resolves false when cancelled', async () => {
        const { confirm, handleCancel } = useConfirmation();

        const promise = confirm({ message: 'Are you sure?' });
        handleCancel();

        await expect(promise).resolves.toBe(false);
    });

    it('sets confirmation options', () => {
        const { title, message, confirmLabel, isDestructive, confirm } = useConfirmation();

        confirm({
            title: 'Delete Item',
            message: 'This cannot be undone',
            confirmLabel: 'Delete',
            destructive: true,
        });

        expect(title.value).toBe('Delete Item');
        expect(message.value).toBe('This cannot be undone');
        expect(confirmLabel.value).toBe('Delete');
        expect(isDestructive.value).toBe(true);
    });
});
```

**File: `resources/js/composables/__tests__/useSearch.test.ts`**
```typescript
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useSearch } from '../ui/useSearch';
import { router } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
    },
}));

describe('useSearch', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('debounces search requests', async () => {
        const { query } = useSearch({ debounceMs: 300 });

        query.value = 'test';
        expect(router.get).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);
        expect(router.get).toHaveBeenCalled();
    });

    it('uses custom param name', async () => {
        const { query } = useSearch({ paramName: 'q', debounceMs: 0 });

        query.value = 'hello';
        vi.advanceTimersByTime(100);

        expect(router.get).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ q: 'hello' }),
            expect.any(Object)
        );
    });

    it('clears search', () => {
        const { query, clear } = useSearch({ initial: 'hello' });

        expect(query.value).toBe('hello');

        clear();
        expect(query.value).toBe('');
    });

    it('respects minimum length', async () => {
        const { query } = useSearch({ minLength: 3, debounceMs: 0 });

        query.value = 'ab';
        vi.advanceTimersByTime(100);
        expect(router.get).not.toHaveBeenCalled();

        query.value = 'abc';
        vi.advanceTimersByTime(100);
        expect(router.get).toHaveBeenCalled();
    });
});
```

---

## Component Testing

### Testing Isolated Components

**File: `resources/js/components/features/shared/__tests__/StatusBadge.test.ts`**
```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusBadge from '../StatusBadge.vue';

describe('StatusBadge', () => {
    it('renders course status correctly', () => {
        const wrapper = mount(StatusBadge, {
            props: {
                type: 'course',
                status: 'published',
            },
        });

        expect(wrapper.text()).toBe('Dipublikasikan');
        expect(wrapper.classes()).toContain('bg-green-100');
        expect(wrapper.classes()).toContain('text-green-700');
    });

    it('renders enrollment status correctly', () => {
        const wrapper = mount(StatusBadge, {
            props: {
                type: 'enrollment',
                status: 'active',
            },
        });

        expect(wrapper.text()).toBe('Aktif');
        expect(wrapper.classes()).toContain('bg-blue-100');
    });

    it('renders difficulty level correctly', () => {
        const wrapper = mount(StatusBadge, {
            props: {
                type: 'difficulty',
                status: 'beginner',
            },
        });

        expect(wrapper.text()).toBe('Pemula');
    });

    it('applies size classes', () => {
        const wrapper = mount(StatusBadge, {
            props: {
                type: 'course',
                status: 'draft',
                size: 'lg',
            },
        });

        expect(wrapper.classes()).toContain('px-4');
        expect(wrapper.classes()).toContain('py-1.5');
    });
});
```

**File: `resources/js/components/features/shared/__tests__/EmptyState.test.ts`**
```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EmptyState from '../EmptyState.vue';
import { Inbox } from 'lucide-vue-next';

describe('EmptyState', () => {
    it('renders title and description', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No items found',
                description: 'Try adjusting your search',
            },
        });

        expect(wrapper.text()).toContain('No items found');
        expect(wrapper.text()).toContain('Try adjusting your search');
    });

    it('renders action button when provided', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No items',
                actionLabel: 'Create Item',
            },
        });

        const button = wrapper.find('button');
        expect(button.exists()).toBe(true);
        expect(button.text()).toBe('Create Item');
    });

    it('emits action event when button clicked', async () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No items',
                actionLabel: 'Create',
            },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('action')).toBeTruthy();
    });

    it('does not render action button without label', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No items',
            },
        });

        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('uses custom icon when provided', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No items',
                icon: Inbox,
            },
        });

        // Icon should be rendered
        expect(wrapper.find('svg').exists()).toBe(true);
    });
});
```

### Testing User Interactions

**File: `resources/js/components/features/assessment/__tests__/MultipleChoice.test.ts`**
```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MultipleChoice from '../QuestionRenderer/MultipleChoice.vue';

const mockQuestion = {
    id: 1,
    type: 'multiple_choice',
    question_text: 'What is 2 + 2?',
    points: 10,
    position: 1,
    options: [
        { id: 'a', text: '3', is_correct: false },
        { id: 'b', text: '4', is_correct: true },
        { id: 'c', text: '5', is_correct: false },
    ],
};

describe('MultipleChoice', () => {
    it('renders all options', () => {
        const wrapper = mount(MultipleChoice, {
            props: {
                question: mockQuestion,
                modelValue: undefined,
            },
        });

        expect(wrapper.text()).toContain('3');
        expect(wrapper.text()).toContain('4');
        expect(wrapper.text()).toContain('5');
    });

    it('selects option on click', async () => {
        const wrapper = mount(MultipleChoice, {
            props: {
                question: mockQuestion,
                modelValue: undefined,
            },
        });

        const options = wrapper.findAll('[class*="cursor-pointer"]');
        await options[1].trigger('click'); // Click option "4"

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')![0]).toEqual(['b']);
    });

    it('shows selected state', () => {
        const wrapper = mount(MultipleChoice, {
            props: {
                question: mockQuestion,
                modelValue: 'b',
            },
        });

        const selectedOption = wrapper.findAll('[class*="border-primary"]');
        expect(selectedOption.length).toBe(1);
    });

    it('does not allow selection in readonly mode', async () => {
        const wrapper = mount(MultipleChoice, {
            props: {
                question: mockQuestion,
                modelValue: 'a',
                readonly: true,
            },
        });

        const options = wrapper.findAll('[class*="cursor-pointer"]');
        await options[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeFalsy();
    });

    it('shows correct/incorrect feedback', () => {
        const wrapper = mount(MultipleChoice, {
            props: {
                question: mockQuestion,
                modelValue: 'a', // Wrong answer
                readonly: true,
                showFeedback: true,
            },
        });

        // Should show green for correct answer
        expect(wrapper.html()).toContain('border-green-500');
        // Should show red for selected wrong answer
        expect(wrapper.html()).toContain('border-red-500');
    });
});
```

---

## Integration Testing

### Testing with Mocked Inertia

**File: `resources/js/tests/helpers/inertia.ts`**
```typescript
import { vi } from 'vitest';
import { config } from '@vue/test-utils';
import type { PageProps } from '@/types';

export function createMockPage<T extends Record<string, unknown>>(
    props: T
): { props: T & Partial<PageProps> } {
    return {
        props: {
            auth: { user: { id: 1, name: 'Test User', email: 'test@example.com' } },
            flash: {},
            sidebarOpen: true,
            name: 'Test App',
            quote: { message: 'Test quote', author: 'Test Author' },
            ...props,
        },
    };
}

export function mockInertiaRouter() {
    return {
        visit: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        reload: vi.fn(),
    };
}

export function setupInertiaStubs() {
    config.global.stubs = {
        Link: {
            template: '<a :href="href"><slot /></a>',
            props: ['href'],
        },
        Head: {
            template: '<div />',
            props: ['title'],
        },
    };
}
```

### Testing Page Components

**File: `resources/js/pages/courses/__tests__/Index.test.ts`**
```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createMockPage, setupInertiaStubs } from '@/tests/helpers/inertia';
import CoursesIndex from '../Index.vue';

// Mock composables
vi.mock('@/composables/ui/useSearch', () => ({
    useSearch: () => ({
        query: { value: '' },
        isSearching: { value: false },
        clear: vi.fn(),
    }),
}));

const mockCourses = {
    data: [
        {
            id: 1,
            title: 'Laravel Basics',
            slug: 'laravel-basics',
            status: 'published',
            difficulty_level: 'beginner',
            lessons_count: 10,
            enrollments_count: 50,
            instructor: { id: 1, name: 'John Doe' },
        },
        {
            id: 2,
            title: 'Vue Advanced',
            slug: 'vue-advanced',
            status: 'draft',
            difficulty_level: 'advanced',
            lessons_count: 15,
            enrollments_count: 0,
            instructor: { id: 2, name: 'Jane Smith' },
        },
    ],
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 2,
        from: 1,
        to: 2,
        links: [],
        path: '/courses',
    },
    links: {
        first: '/courses?page=1',
        last: '/courses?page=1',
        prev: null,
        next: null,
    },
};

describe('CoursesIndex', () => {
    beforeEach(() => {
        setupInertiaStubs();
    });

    it('renders course list', () => {
        const page = createMockPage({
            courses: mockCourses,
            categories: [],
            tags: [],
        });

        const wrapper = mount(CoursesIndex, {
            props: page.props,
        });

        expect(wrapper.text()).toContain('Laravel Basics');
        expect(wrapper.text()).toContain('Vue Advanced');
    });

    it('shows empty state when no courses', () => {
        const page = createMockPage({
            courses: { ...mockCourses, data: [], meta: { ...mockCourses.meta, total: 0 } },
            categories: [],
            tags: [],
        });

        const wrapper = mount(CoursesIndex, {
            props: page.props,
        });

        expect(wrapper.text()).toContain('Tidak ada kursus');
    });

    it('displays course status badges', () => {
        const page = createMockPage({
            courses: mockCourses,
            categories: [],
            tags: [],
        });

        const wrapper = mount(CoursesIndex, {
            props: page.props,
        });

        expect(wrapper.text()).toContain('Dipublikasikan');
        expect(wrapper.text()).toContain('Draf');
    });
});
```

---

## Test Utilities

**File: `resources/js/tests/factories/course.ts`**
```typescript
import type { Course, CourseListItem, Section, Lesson } from '@/types';

let courseIdCounter = 0;
let sectionIdCounter = 0;
let lessonIdCounter = 0;

export function createCourse(overrides: Partial<Course> = {}): Course {
    return {
        id: ++courseIdCounter,
        title: `Test Course ${courseIdCounter}`,
        slug: `test-course-${courseIdCounter}`,
        description: 'Test course description',
        short_description: 'Short description',
        thumbnail: null,
        preview_video: null,
        status: 'draft',
        difficulty_level: 'beginner',
        estimated_duration: 60,
        is_featured: false,
        price: 0,
        instructor_id: 1,
        category_id: 1,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    };
}

export function createCourseListItem(overrides: Partial<CourseListItem> = {}): CourseListItem {
    return {
        id: ++courseIdCounter,
        title: `Test Course ${courseIdCounter}`,
        slug: `test-course-${courseIdCounter}`,
        thumbnail: null,
        status: 'draft',
        difficulty_level: 'beginner',
        estimated_duration: 60,
        price: 0,
        lessons_count: 10,
        enrollments_count: 0,
        instructor: { id: 1, name: 'Test Instructor', avatar: undefined },
        category: { id: 1, name: 'Test Category' },
        ...overrides,
    };
}

export function createSection(overrides: Partial<Section> = {}): Section {
    return {
        id: ++sectionIdCounter,
        course_id: 1,
        title: `Section ${sectionIdCounter}`,
        description: null,
        position: sectionIdCounter,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        lessons: [],
        ...overrides,
    };
}

export function createLesson(overrides: Partial<Lesson> = {}): Lesson {
    return {
        id: ++lessonIdCounter,
        section_id: 1,
        title: `Lesson ${lessonIdCounter}`,
        slug: `lesson-${lessonIdCounter}`,
        content_type: 'text',
        content: null,
        description: null,
        duration: null,
        position: lessonIdCounter,
        is_preview: false,
        is_mandatory: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    };
}

export function resetFactories(): void {
    courseIdCounter = 0;
    sectionIdCounter = 0;
    lessonIdCounter = 0;
}
```

---

## Test Scripts

**Add to `package.json`:**
```json
{
    "scripts": {
        "test": "vitest",
        "test:ui": "vitest --ui",
        "test:run": "vitest run",
        "test:coverage": "vitest run --coverage"
    }
}
```

---

## Checklist

### Setup
- [ ] Install testing dependencies
- [ ] Configure `vitest.config.ts`
- [ ] Create test setup file
- [ ] Create test helpers and factories

### Unit Tests
- [ ] Test all formatters in `lib/formatters.ts`
- [ ] Test all string utilities in `lib/string.ts`
- [ ] Test all date utilities in `lib/date.ts`
- [ ] Test type guards in `types/guards.ts`

### Composable Tests
- [ ] Test `useModal`
- [ ] Test `useConfirmation`
- [ ] Test `useToast`
- [ ] Test `useSearch`
- [ ] Test `usePagination`
- [ ] Test `useProgressTracking`

### Component Tests
- [ ] Test `StatusBadge`
- [ ] Test `EmptyState`
- [ ] Test `LoadingState`
- [ ] Test `MultipleChoice`
- [ ] Test `TrueFalse`
- [ ] Test `CourseCard`

### Integration Tests
- [ ] Test `courses/Index.vue`
- [ ] Test `courses/Show.vue`
- [ ] Test `lessons/Show.vue`
- [ ] Test `assessments/Take.vue`

---

## Success Criteria

| Metric | Before | After |
|--------|--------|-------|
| Test coverage | 0% | 70%+ |
| Unit tests | 0 | 50+ |
| Component tests | 0 | 30+ |
| Integration tests | 0 | 10+ |

---

## Next Phase

After completing Testing Strategy, proceed to [Phase 7: Performance Optimization](./07-PERFORMANCE.md).
