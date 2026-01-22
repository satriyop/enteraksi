---
name: enteraksi-multistate-components
description: Multi-state Vue 3 component patterns for Enteraksi LMS. Use when building components that display different UI based on status (enrollment states, progress states, entity states).
triggers:
  - multi-state component
  - status display
  - enrollment card
  - conditional rendering
  - v-if v-else-if
  - status badge
  - enrollment status
  - progress display
  - state-based UI
---

# Enteraksi Multi-State Vue Components

## When to Use This Skill

- Building cards that show different UI based on entity status
- Creating enrollment/progress display components
- Components with 3+ distinct visual states
- Status badges with conditional styling

## The Pattern

Multi-state components:
1. Define TypeScript interface for the status data
2. Use computed properties for clean state checks
3. Use `v-if`/`v-else-if`/`v-else` for mutually exclusive states
4. Extract repeated elements (like progress bars) to shared spots

## Real Example: CourseEnrollmentCard

```vue
<script setup lang="ts">
// =============================================================================
// CourseEnrollmentCard Component
// Displays enrollment status and actions in course detail sidebar
// =============================================================================

import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ProgressBar } from '@/components/features/shared';
import { CheckCircle, RotateCcw, Trophy, XCircle } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface UserEnrollment {
    id: number;
    status: 'active' | 'completed' | 'dropped';
    enrolled_at: string;
    progress_percentage: number;
}

interface AssessmentStats {
    total: number;
    passed: number;
    pending: number;
    required_total: number;
    required_passed: number;
    required_pending: number;
    all_required_passed: boolean;
}

interface Props {
    courseId: number;
    enrollment?: UserEnrollment | null;
    canEnroll?: boolean;
    assessmentStats?: AssessmentStats | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    enrollment: null,
    canEnroll: true,
    assessmentStats: null,
});

// =============================================================================
// State
// =============================================================================

const isEnrolling = ref(false);
const isReenrolling = ref(false);

// =============================================================================
// Computed - State Checks
// =============================================================================

// Primary state checks - mutually exclusive
const isActive = computed(() => props.enrollment?.status === 'active');
const isCompleted = computed(() => props.enrollment?.status === 'completed');
const isDropped = computed(() => props.enrollment?.status === 'dropped');

// Derived state checks
const hasProgress = computed(() => (props.enrollment?.progress_percentage ?? 0) > 0);
const hasPendingAssessments = computed(() =>
    props.assessmentStats && props.assessmentStats.required_pending > 0
);
const hasAssessments = computed(() =>
    props.assessmentStats && props.assessmentStats.required_total > 0
);

// =============================================================================
// Methods
// =============================================================================

const handleEnroll = () => {
    isEnrolling.value = true;
    router.post(`/courses/${props.courseId}/enroll`, {}, {
        onFinish: () => {
            isEnrolling.value = false;
        },
    });
};
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <!-- Active Enrollment -->
            <div v-if="isActive" class="space-y-4">
                <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                    <CheckCircle class="h-5 w-5" />
                    <span class="font-medium">Anda sudah terdaftar</span>
                </div>
                <ProgressBar :value="enrollment?.progress_percentage || 0" />
                <!-- Assessment status section... -->
            </div>

            <!-- Completed Enrollment -->
            <div v-else-if="isCompleted" class="space-y-4">
                <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                    <Trophy class="h-5 w-5" />
                    <span class="font-medium">Kursus Selesai!</span>
                </div>
                <ProgressBar :value="100" />
            </div>

            <!-- Dropped Enrollment -->
            <div v-else-if="isDropped" class="space-y-4">
                <div class="flex items-center gap-2 text-orange-600 dark:text-orange-400">
                    <XCircle class="h-5 w-5" />
                    <span class="font-medium">Pendaftaran Dibatalkan</span>
                </div>
                <Button @click="handleReenroll">
                    <RotateCcw class="mr-2 h-4 w-4" />
                    Lanjutkan Belajar
                </Button>
            </div>

            <!-- Not Enrolled -->
            <div v-else class="space-y-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary">Gratis</div>
                </div>
                <Button v-if="canEnroll" @click="handleEnroll" :disabled="isEnrolling">
                    {{ isEnrolling ? 'Mendaftar...' : 'Daftar Sekarang' }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
```

## State Definitions

### Status Interface Pattern

```typescript
// Define all possible states explicitly
interface UserEnrollment {
    id: number;
    status: 'active' | 'completed' | 'dropped';  // Union type for states
    progress_percentage: number;
}

// Stats interface with computed fields from backend
interface AssessmentStats {
    total: number;
    passed: number;
    pending: number;
    required_total: number;
    required_passed: number;
    required_pending: number;        // Computed by backend
    all_required_passed: boolean;    // Computed by backend
}
```

### Computed State Checks

```typescript
// Primary states - exactly one is true (or all false if not enrolled)
const isActive = computed(() => props.enrollment?.status === 'active');
const isCompleted = computed(() => props.enrollment?.status === 'completed');
const isDropped = computed(() => props.enrollment?.status === 'dropped');

// Derived states - combine multiple conditions
const hasProgress = computed(() =>
    (props.enrollment?.progress_percentage ?? 0) > 0
);

const hasPendingAssessments = computed(() =>
    props.assessmentStats && props.assessmentStats.required_pending > 0
);

// Nullable check pattern
const hasAssessments = computed(() =>
    props.assessmentStats && props.assessmentStats.required_total > 0
);
```

## Template Structure

### Mutually Exclusive States

```vue
<template>
    <!-- State A -->
    <div v-if="isActive">
        <!-- Active state content -->
    </div>

    <!-- State B -->
    <div v-else-if="isCompleted">
        <!-- Completed state content -->
    </div>

    <!-- State C -->
    <div v-else-if="isDropped">
        <!-- Dropped state content -->
    </div>

    <!-- Default state (not enrolled) -->
    <div v-else>
        <!-- Not enrolled content -->
    </div>
</template>
```

### Nested Conditional Content

```vue
<div v-if="isActive" class="space-y-4">
    <!-- Always shown in active state -->
    <ProgressBar :value="enrollment?.progress_percentage || 0" />

    <!-- Conditional within state -->
    <div v-if="hasAssessments" class="rounded-lg bg-muted/50 p-3">
        <div v-if="hasPendingAssessments" class="text-orange-600">
            {{ assessmentStats?.required_pending }} assessment wajib belum selesai
        </div>
        <div v-else class="text-green-600">
            Semua assessment wajib sudah lulus
        </div>
    </div>
</div>
```

## Status Badge Pattern

For course cards in browse pages:

```vue
<script setup lang="ts">
const statusConfig = computed(() => {
    if (!props.enrollmentInfo) return null;

    switch (props.enrollmentInfo.status) {
        case 'completed':
            return { label: 'Selesai', class: 'bg-green-500 text-white' };
        case 'active':
            return { label: 'Sedang Dipelajari', class: 'bg-blue-500 text-white' };
        case 'dropped':
            return { label: 'Dibatalkan', class: 'bg-gray-500 text-white' };
        default:
            return null;
    }
});
</script>

<template>
    <Badge v-if="statusConfig" :class="statusConfig.class">
        {{ statusConfig.label }}
    </Badge>
</template>
```

## Dynamic CTA Pattern

```vue
<script setup lang="ts">
const ctaConfig = computed(() => {
    if (!props.enrollmentInfo) {
        return { label: 'Lihat Detail', variant: 'default' as const };
    }

    switch (props.enrollmentInfo.status) {
        case 'active':
            return { label: 'Lanjutkan', variant: 'default' as const };
        case 'completed':
            return { label: 'Tinjau', variant: 'secondary' as const };
        case 'dropped':
            return { label: 'Lanjutkan Lagi', variant: 'outline' as const };
        default:
            return { label: 'Lihat Detail', variant: 'default' as const };
    }
});
</script>

<template>
    <Button :variant="ctaConfig.variant">
        {{ ctaConfig.label }}
    </Button>
</template>
```

## Loading State Pattern

```vue
<script setup lang="ts">
const isEnrolling = ref(false);
const isReenrolling = ref(false);

const handleEnroll = () => {
    isEnrolling.value = true;
    router.post(`/courses/${props.courseId}/enroll`, {}, {
        onFinish: () => {
            isEnrolling.value = false;
        },
    });
};
</script>

<template>
    <Button
        @click="handleEnroll"
        :disabled="isEnrolling"
    >
        {{ isEnrolling ? 'Mendaftar...' : 'Daftar Sekarang' }}
    </Button>
</template>
```

## Props with Defaults

```typescript
interface Props {
    courseId: number;
    enrollment?: UserEnrollment | null;    // Optional, nullable
    canEnroll?: boolean;                    // Optional with default
    assessmentStats?: AssessmentStats | null;
}

const props = withDefaults(defineProps<Props>(), {
    enrollment: null,
    canEnroll: true,
    assessmentStats: null,
});
```

## Key Principles

1. **Define status as union type** - `'active' | 'completed' | 'dropped'`
2. **Use computed for state checks** - Clean, reusable, cached
3. **v-if/v-else-if for exclusive states** - Only one renders
4. **Nested v-if for variations** - Sub-states within a main state
5. **Config objects for badges/CTAs** - Centralize styling logic
6. **Loading refs per action** - Separate `isEnrolling`, `isReenrolling`

## Files to Reference

```
resources/js/components/courses/CourseEnrollmentCard.vue   # Multi-state card
resources/js/components/courses/BrowseCourseCard.vue       # Card with status badge
resources/js/pages/courses/Detail.vue                      # Page passing stats
resources/js/pages/courses/Browse.vue                      # List with enrollment map
```
