# Enteraksi Component Architecture Skill

Guidelines and patterns for component extraction and page refactoring in the Enteraksi LMS application.

---

## Target Metrics

| Metric | Target | Acceptable |
|--------|--------|------------|
| Page lines | < 200 | < 250 |
| Component lines | < 150 | < 200 |
| Type definitions | ~30-50 lines per page | Acceptable overhead |

---

## When to Extract Components

### Extract When:
- **Repeated patterns** across 2+ pages (sidebar forms, card layouts)
- **Sidebar sections** with status/visibility controls
- **Card-based displays** with consistent structure (info cards, tips cards)
- **Navigation elements** (question nav, lesson nav)
- **List item cards** that render in loops

### Do NOT Extract When:
- Component would be < 30 lines (too small)
- Pattern appears only once
- Page is already < 220 lines with existing components
- Extraction would require passing 8+ props (over-engineered)

---

## Component Naming Conventions

```
{Domain}{Context}{Type}.vue

Examples:
- AssessmentFormSidebar.vue    (Domain: Assessment, Context: Form, Type: Sidebar)
- AttemptInfoCard.vue          (Domain: Attempt, Context: Info, Type: Card)
- LearningPathCourseCard.vue   (Domain: LearningPath, Context: Course, Type: Card)
- BrowseCourseCard.vue         (Domain: Browse, Context: Course, Type: Card)
```

### Type Suffixes:
- `Card` - Self-contained display component
- `Sidebar` - Sidebar section component
- `Form` - Form-focused component
- `List` - List container component
- `Header` - Header section component

---

## Component Organization

```
resources/js/components/
├── assessments/
│   ├── AssessmentFormSidebar.vue      # Shared create/edit sidebar
│   ├── AssessmentToggleOption.vue     # Reusable toggle with icon
│   ├── AttemptInfoCard.vue            # Attempt stats display
│   ├── AttemptNavigationCard.vue      # Question navigation
│   └── AttemptTipsCard.vue            # Tips display
├── courses/
│   ├── BrowseCourseCard.vue           # Course card for browsing
│   ├── CourseContentOutline.vue       # Curriculum accordion
│   ├── CourseEnrollmentCard.vue       # Enrollment CTA
│   └── CourseMetaCard.vue             # Course metadata
└── learning_paths/
    └── LearningPathCourseCard.vue     # Course in learning path
```

---

## Consolidation Patterns

### Pattern 1: Shared Form Sidebar (Create/Edit)

Instead of separate `CreateSidebar` and `EditSidebar`, use configurable labels:

```vue
<!-- AssessmentFormSidebar.vue -->
<script setup lang="ts">
interface Props {
    cancelHref: string;
    processing: boolean;
    submitLabel?: string;      // "Simpan Penilaian" for create
    processingLabel?: string;  // "Simpan Perubahan" for edit
    errors: { status?: string; visibility?: string };
}

const status = defineModel<AssessmentStatus>('status', { required: true });
const visibility = defineModel<AssessmentVisibility>('visibility', { required: true });
</script>
```

**Usage in Create.vue:**
```vue
<AssessmentFormSidebar
    v-model:status="form.status"
    v-model:visibility="form.visibility"
    :cancel-href="`/courses/${course.id}/assessments`"
    :processing="processing"
    :errors="{ status: errors.status, visibility: errors.visibility }"
    submit-label="Simpan Penilaian"
/>
```

**Usage in Edit.vue:**
```vue
<AssessmentFormSidebar
    v-model:status="form.status"
    v-model:visibility="form.visibility"
    :cancel-href="`/courses/${course.id}/assessments/${assessment.id}`"
    :processing="processing"
    :errors="{ status: errors.status, visibility: errors.visibility }"
    submit-label="Simpan Perubahan"
/>
```

### Pattern 2: Reusable Toggle Option

```vue
<!-- AssessmentToggleOption.vue -->
<script setup lang="ts">
import { Switch } from '@/components/ui/switch';
import { type Component } from 'vue';

interface Props {
    id: string;
    name: string;
    icon: Component;
    title: string;
    description: string;
}

defineProps<Props>();
const modelValue = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="flex items-center justify-between rounded-lg border p-4">
        <div class="flex items-center gap-3">
            <component :is="icon" class="h-5 w-5 text-muted-foreground" />
            <div>
                <h4 class="font-medium">{{ title }}</h4>
                <p class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
        </div>
        <Switch :id="id" :name="name" v-model="modelValue" />
    </div>
</template>
```

**Usage:**
```vue
<AssessmentToggleOption
    id="shuffle_questions"
    name="shuffle_questions"
    :icon="Shuffle"
    title="Acak Pertanyaan"
    description="Acak urutan pertanyaan untuk setiap peserta"
    v-model="form.shuffle_questions"
/>
```

### Pattern 3: Info Card with Icon-Label Pairs

```vue
<!-- AttemptInfoCard.vue -->
<script setup lang="ts">
interface Props {
    attemptNumber: number;
    timeElapsed: string;
    timeLeft?: string | null;
    hasTimeLimit: boolean;
    passingScore: number;
    totalQuestions: number;
}
</script>

<template>
    <Card>
        <CardHeader><CardTitle>Informasi Percobaan</CardTitle></CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <ListOrdered class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Percobaan Ke-</p>
                    <p class="font-medium">{{ attemptNumber }}</p>
                </div>
            </div>
            <!-- More icon-label pairs... -->
        </CardContent>
    </Card>
</template>
```

### Pattern 4: Navigation Card with Emit

```vue
<!-- AttemptNavigationCard.vue -->
<script setup lang="ts">
interface Props {
    totalQuestions: number;
}

defineProps<Props>();

const emit = defineEmits<{
    navigate: [index: number];
}>();
</script>

<template>
    <Card>
        <CardContent>
            <div class="grid grid-cols-5 gap-2">
                <Button
                    v-for="index in totalQuestions"
                    :key="index"
                    variant="outline"
                    size="sm"
                    @click="emit('navigate', index - 1)"
                >
                    {{ index }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
```

---

## Gotchas & Solutions

### 1. Type Definitions Stay in Pages

**Problem:** Extracting types to shared files creates import complexity.

**Solution:** Keep page-specific types in the page file. ~30-50 lines of types is acceptable.

```vue
// In page file - this is OK
interface AssessmentFormData {
    title: string;
    description: string;
    // ...
}
```

### 2. Computed Properties: Extract Logic, Not Computation

**Problem:** Computed properties depend on props/state, hard to extract.

**Solution:** Extract helper functions (pure), keep computed in page.

```typescript
// Extract to utils (pure function)
export const getDifficultyColor = (level: DifficultyLevel) => {
    const colors = DIFFICULTY_COLORS[level];
    return colors ? `${colors.bg} ${colors.text}` : '';
};

// Keep in page (depends on props)
const totalDuration = computed(() => {
    const minutes = props.course.manual_duration_minutes ?? props.course.estimated_duration_minutes;
    return formatDuration(minutes, 'long');
});
```

### 3. v-model with defineModel

**Problem:** Two-way binding for multiple values.

**Solution:** Use `defineModel` with named models:

```vue
<script setup>
const status = defineModel<AssessmentStatus>('status', { required: true });
const visibility = defineModel<AssessmentVisibility>('visibility', { required: true });
</script>

<!-- Parent usage -->
<Component
    v-model:status="form.status"
    v-model:visibility="form.visibility"
/>
```

### 4. Error Object Mapping

**Problem:** Parent errors object has different shape than component expects.

**Solution:** Map errors explicitly in parent:

```vue
<!-- Don't pass entire errors object -->
<Component :errors="errors" />

<!-- Map specific fields -->
<Component :errors="{ status: errors.status, visibility: errors.visibility }" />
```

### 5. Icon Components as Props

**Problem:** Passing Lucide icons as props.

**Solution:** Use `Component` type and dynamic component:

```vue
<script setup>
import { type Component } from 'vue';

interface Props {
    icon: Component;
}
</script>

<template>
    <component :is="icon" class="h-5 w-5" />
</template>
```

---

## Refactoring Checklist

Before extracting a component:

- [ ] Pattern appears 2+ times OR section is 50+ lines
- [ ] Component will have < 8 props
- [ ] Component has clear single responsibility
- [ ] Name follows `{Domain}{Context}{Type}` convention

After extracting:

- [ ] Page line count reduced meaningfully (20+ lines)
- [ ] `npm run build` passes
- [ ] No TypeScript errors
- [ ] UI renders correctly
- [ ] Existing functionality preserved

---

## Quick Reference: Line Count Targets

| File Type | Excellent | Good | Needs Work |
|-----------|-----------|------|------------|
| Page | < 150 | < 200 | > 250 |
| Component | < 100 | < 150 | > 200 |
| Composable | < 50 | < 100 | > 150 |

---

## Commands

```bash
# Check page line counts
wc -l resources/js/pages/**/*.vue | sort -n | tail -20

# Check component line counts
wc -l resources/js/components/**/*.vue | sort -n | tail -20

# Build verification
npm run build
```
