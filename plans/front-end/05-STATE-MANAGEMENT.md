# Phase 5: State Management

## Overview

This phase establishes clear patterns for managing state across the application. With Inertia.js handling server state, the focus is on client-side UI state, shared state between components, and optimistic updates.

**Duration:** 1-2 weeks
**Risk Level:** Low
**Dependencies:** Phase 4 (Composables)

---

## State Categories

### 1. Server State (Managed by Inertia)
- Page props from Laravel
- Form state via `useForm`
- Navigation state

### 2. Client State (Our Focus)
- UI state (modals, sidebars, tabs)
- User preferences (theme, language)
- Transient state (search filters, sorting)
- Optimistic updates

### 3. Shared State
- Toast notifications
- Global loading state
- User session info

---

## State Management Patterns

### Pattern 1: Inertia Page Props (Server State)

**When to use:** Data from Laravel controllers

```vue
<script setup lang="ts">
import type { Course, User } from '@/types';

interface Props {
    course: Course;
    user: User;
    canEdit: boolean;
}

const props = defineProps<Props>();

// Props are reactive and update on Inertia visits
</script>
```

### Pattern 2: Component Local State

**When to use:** State specific to one component

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';

// Local state
const isExpanded = ref(false);
const selectedTab = ref('details');

// Computed from local state
const tabIndex = computed(() =>
    ['details', 'curriculum', 'reviews'].indexOf(selectedTab.value)
);
</script>
```

### Pattern 3: Composable State (Reusable Logic)

**When to use:** State + logic reused across components

```typescript
// composables/ui/useTabs.ts
import { ref, computed } from 'vue';

export function useTabs<T extends string>(tabs: T[], initialTab?: T) {
    const currentTab = ref<T>(initialTab ?? tabs[0]);

    const currentIndex = computed(() =>
        tabs.indexOf(currentTab.value)
    );

    const isFirst = computed(() => currentIndex.value === 0);
    const isLast = computed(() => currentIndex.value === tabs.length - 1);

    function setTab(tab: T): void {
        if (tabs.includes(tab)) {
            currentTab.value = tab;
        }
    }

    function next(): void {
        if (!isLast.value) {
            currentTab.value = tabs[currentIndex.value + 1];
        }
    }

    function previous(): void {
        if (!isFirst.value) {
            currentTab.value = tabs[currentIndex.value - 1];
        }
    }

    return {
        currentTab,
        currentIndex,
        isFirst,
        isLast,
        setTab,
        next,
        previous,
    };
}
```

### Pattern 4: Provide/Inject (Component Tree State)

**When to use:** State shared within a component subtree

```typescript
// stores/courseEditor.ts
import { provide, inject, ref, computed, type InjectionKey, type Ref } from 'vue';
import type { Course, Section, Lesson } from '@/types';

interface CourseEditorState {
    course: Ref<Course>;
    sections: Ref<Section[]>;
    isDirty: Ref<boolean>;
    selectedSectionId: Ref<number | null>;
    selectedLessonId: Ref<number | null>;
}

interface CourseEditorActions {
    selectSection: (id: number) => void;
    selectLesson: (id: number) => void;
    addSection: (section: Section) => void;
    updateSection: (id: number, data: Partial<Section>) => void;
    deleteSection: (id: number) => void;
    addLesson: (sectionId: number, lesson: Lesson) => void;
    updateLesson: (id: number, data: Partial<Lesson>) => void;
    deleteLesson: (id: number) => void;
    reorderSections: (sectionIds: number[]) => void;
    reorderLessons: (sectionId: number, lessonIds: number[]) => void;
    save: () => Promise<void>;
}

type CourseEditorContext = CourseEditorState & CourseEditorActions;

const CourseEditorKey: InjectionKey<CourseEditorContext> = Symbol('CourseEditor');

export function provideCourseEditor(initialCourse: Course, initialSections: Section[]) {
    const course = ref<Course>(initialCourse);
    const sections = ref<Section[]>(initialSections);
    const isDirty = ref(false);
    const selectedSectionId = ref<number | null>(null);
    const selectedLessonId = ref<number | null>(null);

    // Actions
    function selectSection(id: number) {
        selectedSectionId.value = id;
        selectedLessonId.value = null;
    }

    function selectLesson(id: number) {
        selectedLessonId.value = id;
        // Find and select parent section
        for (const section of sections.value) {
            if (section.lessons?.some(l => l.id === id)) {
                selectedSectionId.value = section.id;
                break;
            }
        }
    }

    function addSection(section: Section) {
        sections.value.push(section);
        isDirty.value = true;
    }

    function updateSection(id: number, data: Partial<Section>) {
        const index = sections.value.findIndex(s => s.id === id);
        if (index > -1) {
            sections.value[index] = { ...sections.value[index], ...data };
            isDirty.value = true;
        }
    }

    function deleteSection(id: number) {
        const index = sections.value.findIndex(s => s.id === id);
        if (index > -1) {
            sections.value.splice(index, 1);
            isDirty.value = true;
            if (selectedSectionId.value === id) {
                selectedSectionId.value = null;
            }
        }
    }

    function addLesson(sectionId: number, lesson: Lesson) {
        const section = sections.value.find(s => s.id === sectionId);
        if (section) {
            section.lessons = section.lessons ?? [];
            section.lessons.push(lesson);
            isDirty.value = true;
        }
    }

    function updateLesson(id: number, data: Partial<Lesson>) {
        for (const section of sections.value) {
            const lesson = section.lessons?.find(l => l.id === id);
            if (lesson) {
                Object.assign(lesson, data);
                isDirty.value = true;
                break;
            }
        }
    }

    function deleteLesson(id: number) {
        for (const section of sections.value) {
            const index = section.lessons?.findIndex(l => l.id === id) ?? -1;
            if (index > -1) {
                section.lessons!.splice(index, 1);
                isDirty.value = true;
                if (selectedLessonId.value === id) {
                    selectedLessonId.value = null;
                }
                break;
            }
        }
    }

    function reorderSections(sectionIds: number[]) {
        sections.value = sectionIds
            .map(id => sections.value.find(s => s.id === id))
            .filter((s): s is Section => s !== undefined);
        isDirty.value = true;
    }

    function reorderLessons(sectionId: number, lessonIds: number[]) {
        const section = sections.value.find(s => s.id === sectionId);
        if (section && section.lessons) {
            section.lessons = lessonIds
                .map(id => section.lessons!.find(l => l.id === id))
                .filter((l): l is Lesson => l !== undefined);
            isDirty.value = true;
        }
    }

    async function save() {
        // Save to server
        isDirty.value = false;
    }

    const context: CourseEditorContext = {
        course,
        sections,
        isDirty,
        selectedSectionId,
        selectedLessonId,
        selectSection,
        selectLesson,
        addSection,
        updateSection,
        deleteSection,
        addLesson,
        updateLesson,
        deleteLesson,
        reorderSections,
        reorderLessons,
        save,
    };

    provide(CourseEditorKey, context);

    return context;
}

export function useCourseEditor(): CourseEditorContext {
    const context = inject(CourseEditorKey);
    if (!context) {
        throw new Error('useCourseEditor must be used within a CourseEditor provider');
    }
    return context;
}
```

**Usage:**
```vue
<!-- Parent component -->
<script setup lang="ts">
import { provideCourseEditor } from '@/stores/courseEditor';

const props = defineProps<{ course: Course; sections: Section[] }>();

// Provide state to all children
provideCourseEditor(props.course, props.sections);
</script>

<!-- Child component (any depth) -->
<script setup lang="ts">
import { useCourseEditor } from '@/stores/courseEditor';

const { sections, selectSection, isDirty } = useCourseEditor();
</script>
```

### Pattern 5: Global Singleton State

**When to use:** Truly global state (toasts, auth, theme)

```typescript
// stores/global/auth.ts
import { ref, computed, readonly } from 'vue';
import type { User } from '@/types';

// Singleton state (module-level)
const user = ref<User | null>(null);
const isAuthenticated = computed(() => !!user.value);

export function useAuth() {
    function setUser(newUser: User | null) {
        user.value = newUser;
    }

    function hasPermission(permission: string): boolean {
        return user.value?.permissions?.some(p => p.name === permission) ?? false;
    }

    function hasRole(role: string): boolean {
        return user.value?.roles?.some(r => r.name === role) ?? false;
    }

    function logout() {
        user.value = null;
    }

    return {
        user: readonly(user),
        isAuthenticated,
        setUser,
        hasPermission,
        hasRole,
        logout,
    };
}
```

```typescript
// stores/global/theme.ts
import { ref, computed, watchEffect } from 'vue';
import { STORAGE_KEYS } from '@/lib/constants';

type Theme = 'light' | 'dark' | 'system';

const theme = ref<Theme>(
    (localStorage.getItem(STORAGE_KEYS.theme) as Theme) || 'system'
);

const isDark = computed(() => {
    if (theme.value === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return theme.value === 'dark';
});

// Apply theme to document
watchEffect(() => {
    document.documentElement.classList.toggle('dark', isDark.value);
});

export function useTheme() {
    function setTheme(newTheme: Theme) {
        theme.value = newTheme;
        localStorage.setItem(STORAGE_KEYS.theme, newTheme);
    }

    function toggleTheme() {
        setTheme(isDark.value ? 'light' : 'dark');
    }

    return {
        theme,
        isDark,
        setTheme,
        toggleTheme,
    };
}
```

---

## Optimistic Updates

### Pattern: Optimistic Update with Rollback

```typescript
// composables/features/useOptimisticUpdate.ts
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

interface UseOptimisticUpdateOptions<T> {
    /** Current data ref */
    data: Ref<T>;
    /** Function to apply optimistic change */
    optimisticUpdate: (current: T) => T;
    /** Server endpoint */
    endpoint: string;
    /** Request method */
    method?: 'post' | 'put' | 'patch' | 'delete';
    /** Request payload */
    payload?: Record<string, unknown>;
    /** Called on success */
    onSuccess?: () => void;
    /** Called on error */
    onError?: (error: unknown) => void;
}

export function useOptimisticUpdate<T>(options: UseOptimisticUpdateOptions<T>) {
    const {
        data,
        optimisticUpdate,
        endpoint,
        method = 'post',
        payload,
        onSuccess,
        onError,
    } = options;

    const isPending = ref(false);
    const error = ref<string | null>(null);

    async function execute(): Promise<boolean> {
        // Store original for rollback
        const originalData = JSON.parse(JSON.stringify(data.value));

        // Apply optimistic update immediately
        data.value = optimisticUpdate(data.value);
        isPending.value = true;
        error.value = null;

        return new Promise((resolve) => {
            router[method](endpoint, payload ?? {}, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    isPending.value = false;
                    onSuccess?.();
                    resolve(true);
                },
                onError: (errors) => {
                    // Rollback on error
                    data.value = originalData;
                    isPending.value = false;
                    error.value = typeof errors === 'string'
                        ? errors
                        : 'Terjadi kesalahan';
                    onError?.(errors);
                    resolve(false);
                },
            });
        });
    }

    return {
        isPending,
        error,
        execute,
    };
}
```

**Usage Example:**
```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useOptimisticUpdate } from '@/composables/features/useOptimisticUpdate';

interface Todo {
    id: number;
    title: string;
    completed: boolean;
}

const todos = ref<Todo[]>([
    { id: 1, title: 'Learn Vue', completed: false },
]);

function toggleTodo(id: number) {
    const { execute } = useOptimisticUpdate({
        data: todos,
        optimisticUpdate: (current) =>
            current.map(todo =>
                todo.id === id
                    ? { ...todo, completed: !todo.completed }
                    : todo
            ),
        endpoint: `/todos/${id}/toggle`,
        method: 'patch',
    });

    execute();
}
</script>
```

---

## Form State with Inertia

### Enhanced Form Composable

```typescript
// composables/forms/useEnhancedForm.ts
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

interface UseEnhancedFormOptions<T extends Record<string, unknown>> {
    initial: T;
    transform?: (data: T) => Record<string, unknown>;
    validate?: (data: T) => Record<string, string>;
}

export function useEnhancedForm<T extends Record<string, unknown>>(
    options: UseEnhancedFormOptions<T>
) {
    const { initial, transform, validate } = options;

    const form = useForm(initial);
    const touched = ref<Set<keyof T>>(new Set());
    const clientErrors = ref<Record<string, string>>({});

    // Track which fields have been touched
    function touch(field: keyof T) {
        touched.value.add(field);
        validateField(field);
    }

    function validateField(field: keyof T) {
        if (validate) {
            const errors = validate(form.data() as T);
            if (errors[field as string]) {
                clientErrors.value[field as string] = errors[field as string];
            } else {
                delete clientErrors.value[field as string];
            }
        }
    }

    function validateAll(): boolean {
        if (validate) {
            clientErrors.value = validate(form.data() as T);
            return Object.keys(clientErrors.value).length === 0;
        }
        return true;
    }

    // Combined errors (client + server)
    const allErrors = computed(() => ({
        ...clientErrors.value,
        ...form.errors,
    }));

    const hasErrors = computed(() =>
        Object.keys(allErrors.value).length > 0
    );

    const isFieldTouched = (field: keyof T) =>
        touched.value.has(field);

    const getFieldError = (field: keyof T) =>
        isFieldTouched(field) ? allErrors.value[field as string] : undefined;

    async function submit(
        method: 'post' | 'put' | 'patch' | 'delete',
        url: string,
        options?: Record<string, unknown>
    ) {
        // Validate all before submit
        if (!validateAll()) {
            return;
        }

        const data = transform
            ? transform(form.data() as T)
            : form.data();

        await form.transform(() => data)[method](url, options);
    }

    function reset() {
        form.reset();
        touched.value.clear();
        clientErrors.value = {};
    }

    return {
        form,
        touched,
        clientErrors,
        allErrors,
        hasErrors,
        touch,
        validateField,
        validateAll,
        isFieldTouched,
        getFieldError,
        submit,
        reset,
    };
}
```

---

## State Debugging

### DevTools Integration

```typescript
// lib/devtools.ts
import { ref, watch, type Ref } from 'vue';

const stores: Record<string, Ref<unknown>> = {};

export function registerStore(name: string, state: Ref<unknown>) {
    stores[name] = state;

    if (import.meta.env.DEV) {
        watch(state, (newValue) => {
            console.groupCollapsed(`🔄 Store Update: ${name}`);
            console.log('New Value:', newValue);
            console.trace('Stack trace');
            console.groupEnd();
        }, { deep: true });
    }
}

export function getStores() {
    return stores;
}

// Expose to window for debugging
if (import.meta.env.DEV) {
    (window as unknown as { __STORES__: typeof stores }).__STORES__ = stores;
}
```

---

## State Decision Tree

```
Need to manage state?
│
├─ Is it server data (from Laravel)?
│   └─ Yes → Use Inertia page props
│
├─ Is it form data?
│   └─ Yes → Use useForm() or useEnhancedForm()
│
├─ Is it component-specific UI state?
│   └─ Yes → Use local ref/reactive
│
├─ Is it shared within a component tree?
│   └─ Yes → Use provide/inject pattern
│
├─ Is it reusable logic + state?
│   └─ Yes → Create a composable
│
└─ Is it truly global (toasts, auth, theme)?
    └─ Yes → Use singleton composable
```

---

## Checklist

### Patterns Implementation
- [ ] Document all state patterns
- [ ] Create `useEnhancedForm` composable
- [ ] Create `useOptimisticUpdate` composable
- [ ] Create provide/inject examples

### Global Stores
- [ ] Create `stores/global/auth.ts`
- [ ] Create `stores/global/theme.ts`
- [ ] Create `stores/global/toast.ts`

### Feature Stores
- [ ] Create `stores/courseEditor.ts`
- [ ] Create `stores/assessmentAttempt.ts`
- [ ] Create `stores/lessonViewer.ts`

### DevTools
- [ ] Implement state debugging helpers
- [ ] Add logging for development
- [ ] Expose stores to window in dev

---

## Success Criteria

| Metric | Before | After |
|--------|--------|-------|
| State patterns documented | 0 | 5+ |
| Global stores | 0 | 3+ |
| Feature stores | 0 | 3+ |
| State-related bugs | Unknown | Tracked |

---

## Next Phase

After completing State Management, proceed to [Phase 6: Testing Strategy](./06-TESTING.md).
