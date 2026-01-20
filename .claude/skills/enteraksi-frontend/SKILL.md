---
name: enteraksi-frontend
description: Vue 3 + Inertia + TypeScript patterns for Enteraksi LMS. Use when building Vue components, composables, TypeScript types, or frontend features.
triggers:
  - vue component
  - inertia page
  - frontend
  - composable
  - useComposable
  - typescript type
  - interface
  - model type
  - wayfinder
  - type-safe route
  - dark mode
  - tailwind
  - shadcn
---

# Enteraksi Frontend Patterns

## When to Use This Skill

- Creating Vue 3 components or pages
- Writing TypeScript types/interfaces
- Using Inertia.js for navigation and forms
- Creating composables for shared logic
- Using Wayfinder for type-safe routes
- Implementing dark mode support

## Tech Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| Vue | 3.x | Frontend framework (Composition API) |
| TypeScript | 5.x | Type safety |
| Inertia.js | v2 | SPA bridge to Laravel |
| Tailwind CSS | v4 | Utility-first styling |
| Shadcn/vue | - | UI components (reka-ui based) |
| Lucide | - | Icons (`lucide-vue-next`) |
| Wayfinder | v0 | Type-safe Laravel routes |

## Directory Structure

```
resources/js/
├── app.ts                    # Inertia app setup
├── layouts/
│   └── AppLayout.vue         # Main layout with sidebar
├── pages/                    # Inertia pages (mapped to routes)
│   ├── Dashboard.vue
│   ├── courses/
│   │   ├── Index.vue         # List page
│   │   ├── Create.vue        # Create form
│   │   ├── Edit.vue          # Edit form
│   │   └── Show.vue          # Detail page
│   └── {domain}/
├── components/
│   ├── ui/                   # Shadcn components
│   │   ├── button/Button.vue
│   │   └── ...
│   ├── crud/                 # CRUD page components
│   │   ├── PageHeader.vue
│   │   └── FormSection.vue
│   └── {domain}/             # Domain-specific components
├── composables/
│   ├── index.ts              # Export barrel
│   ├── data/                 # Data-fetching composables
│   ├── features/             # Feature-specific composables
│   └── ui/                   # UI utility composables
├── types/
│   ├── index.d.ts            # Main type exports
│   ├── models/               # Domain model types
│   │   ├── common.ts         # Shared types (IDs, timestamps)
│   │   ├── course.ts
│   │   └── user.ts
│   └── api/                  # API response types
└── lib/
    ├── utils.ts              # cn(), debounce, etc.
    ├── formatters.ts         # formatDate, formatDuration
    ├── constants.ts          # Enums, static values
    └── icons.ts              # Icon mappings
```

## Key Patterns

### 1. Page Props Pattern

```typescript
// Define page props interface
import type { AppPageProps, Course, Category } from '@/types';

const props = defineProps<AppPageProps<{
    course: Course;
    categories: Category[];
    can: {
        edit: boolean;
        delete: boolean;
    };
}>>();

// Access props
props.course.title
props.auth.user  // Always available from AppPageProps
props.flash?.success
```

### 2. Inertia Form Component (Vue 3)

```vue
<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/CourseController';

// Wayfinder generates type-safe routes
const formAction = store.form();  // { action: '/courses', method: 'post' }
</script>

<template>
    <Form
        v-bind="formAction"
        #default="{ errors, processing, wasSuccessful }"
    >
        <input type="text" name="title" />
        <div v-if="errors.title" class="text-red-500">{{ errors.title }}</div>

        <Button type="submit" :disabled="processing">
            {{ processing ? 'Menyimpan...' : 'Simpan' }}
        </Button>
    </Form>
</template>
```

### 3. Wayfinder Route Actions

```typescript
// Import controller actions (tree-shakable)
import { show, store, update, index } from '@/actions/App/Http/Controllers/CourseController';

// Get route object with URL and method
show(1)              // { url: '/courses/1', method: 'get' }
show.url(1)          // '/courses/1' (just URL string)

// For Inertia forms
store.form()         // { action: '/courses', method: 'post' }
update.form(course.id)

// With query params
index({ query: { page: 2, filter: 'active' } })

// Named routes (for non-controller routes)
import { show as courseShow } from '@/routes/course';
courseShow(1)        // For 'course.show' named route
```

### 4. Composable Pattern

```typescript
// composables/useLessonProgress.ts
import axios from 'axios';
import { ref, onUnmounted } from 'vue';
import type { LessonProgress } from '@/types';

interface UseLessonProgressOptions {
    courseId: number;
    lessonId: number;
    enrollmentId: number | null;
    initialProgress: LessonProgress | null;
}

export function useLessonProgress(options: UseLessonProgressOptions) {
    const { courseId, lessonId, enrollmentId, initialProgress } = options;

    // State
    const isCompleted = ref(initialProgress?.is_completed ?? false);
    const isSaving = ref(false);

    // Methods
    const saveProgress = async (page: number, total: number) => {
        if (!enrollmentId || isSaving.value) return;

        isSaving.value = true;
        try {
            await axios.patch(`/courses/${courseId}/lessons/${lessonId}/progress`, {
                current_page: page,
                total_pages: total,
            });
        } finally {
            isSaving.value = false;
        }
    };

    // Cleanup on unmount
    onUnmounted(() => {
        // cleanup timers, etc.
    });

    // Return reactive state and methods
    return {
        isCompleted,
        isSaving,
        saveProgress,
    };
}
```

### 5. TypeScript Model Types

```typescript
// types/models/course.ts
import type { Timestamps, CourseId, UserId, CourseStatus } from './common';
import type { User } from './user';

export interface Course extends Timestamps {
    id: CourseId;
    user_id: UserId;
    title: string;
    slug: string;
    short_description: string | null;
    status: CourseStatus;

    // Model accessors (computed in backend)
    duration?: number;
    is_editable?: boolean;

    // Relations (conditionally loaded)
    user?: User;
    sections?: CourseSection[];

    // Aggregates
    lessons_count?: number;
}

// Lighter type for list pages
export interface CourseListItem {
    id: CourseId;
    title: string;
    slug: string;
    status: CourseStatus;
    lessons_count: number;
    user: { id: UserId; name: string };
}

// Type guards
export function isPublished(course: Course): boolean {
    return course.status === 'published';
}
```

### 6. cn() Utility for Tailwind

```typescript
// lib/utils.ts
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

// Usage in component
<div :class="cn('px-4 py-2', isActive && 'bg-blue-500', className)" />
```

### 7. Dark Mode Support

```typescript
// composables/useAppearance.ts
import { ref, watch } from 'vue';

export type Appearance = 'light' | 'dark' | 'system';

const appearance = ref<Appearance>('system');

export function useAppearance() {
    const setAppearance = (mode: Appearance) => {
        appearance.value = mode;
        // Apply to DOM
    };

    return { appearance, setAppearance };
}

// Initialize on app load
export function initializeTheme() {
    // Check localStorage, system preference
}
```

```vue
<!-- Component with dark mode -->
<template>
    <div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        Content adapts to theme
    </div>
</template>
```

### 8. Layout Pattern

```vue
<!-- pages/courses/Index.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, CourseListItem } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<AppPageProps<{
    courses: CourseListItem[];
}>>();
</script>

<template>
    <div class="space-y-6">
        <PageHeader title="Kursus Saya" />
        <!-- content -->
    </div>
</template>
```

### 9. Shadcn Button with CVA

```vue
<!-- components/ui/button/Button.vue -->
<script setup lang="ts">
import { type ButtonVariants, buttonVariants } from '.';
import { cn } from '@/lib/utils';

interface Props {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
    size: 'default',
});
</script>

<template>
    <button :class="cn(buttonVariants({ variant, size }), props.class)">
        <slot />
    </button>
</template>
```

```typescript
// components/ui/button/index.ts
import { cva, type VariantProps } from 'class-variance-authority';

export const buttonVariants = cva(
    'inline-flex items-center justify-center rounded-md font-medium transition-colors',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primary/90',
                destructive: 'bg-destructive text-destructive-foreground',
                outline: 'border border-input bg-background hover:bg-accent',
                ghost: 'hover:bg-accent',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 px-3',
                lg: 'h-11 px-8',
                icon: 'h-10 w-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
```

## Icon Usage

```vue
<script setup lang="ts">
import { Plus, Edit, Trash2 } from 'lucide-vue-next';
</script>

<template>
    <Button>
        <Plus class="mr-2 h-4 w-4" />
        Tambah
    </Button>
</template>
```

## Gotchas & Best Practices

1. **Always type page props** - Use `AppPageProps<{...}>` wrapper
2. **Wayfinder for routes** - Never hardcode URLs
3. **Composables return refs** - Use `ref()` not raw values
4. **Dark mode: always add dark: variants** - Especially for backgrounds, text
5. **cn() for conditional classes** - Never string concatenation
6. **Types in types/ directory** - Not inline in components
7. **defineModel for two-way binding** - For form components
8. **LucideIcon type for icon props** - `import type { LucideIcon } from 'lucide-vue-next'`

## Quick Reference

```bash
# Files to reference
resources/js/app.ts                      # Inertia setup
resources/js/types/index.d.ts            # Type exports
resources/js/types/models/course.ts      # Model type example
resources/js/lib/utils.ts                # cn() and utilities
resources/js/composables/useLessonProgress.ts  # Composable example
resources/js/components/ui/button/       # Shadcn component example
resources/js/layouts/AppLayout.vue       # Main layout

# Generate Wayfinder types
php artisan wayfinder:generate

# Build frontend
npm run build

# Dev with HMR
npm run dev
```
