# Phase 8: Migration Guide

## Overview

This document provides step-by-step guidance for rolling out the frontend refactoring safely. It includes feature flag strategies, rollback procedures, and team onboarding materials.

**Duration:** Ongoing
**Risk Level:** N/A (Reference Document)
**Dependencies:** All previous phases

---

## Rollout Strategy

### Approach: Incremental Migration

We'll use an incremental approach, migrating one area at a time while keeping the application fully functional throughout.

```
Week 1-2:   Foundation (Types, Utilities)      → No visible changes
Week 3-4:   Shared Components                  → Internal improvements
Week 5-8:   Feature Components (one module)   → Gradual replacement
Week 9-12:  Composables Integration           → Logic extraction
Week 13-15: Testing & Performance             → Quality improvements
```

---

## Phase 1: Types Migration

### Step 1: Create Type Files

```bash
# Create directory structure
mkdir -p resources/js/types/models
mkdir -p resources/js/types/api
mkdir -p resources/js/types/components
```

### Step 2: Add Types Alongside Existing Code

1. Create all type files as defined in [01-TYPE-SYSTEM.md](./01-TYPE-SYSTEM.md)
2. **Do not modify existing components yet**
3. Types coexist with inline definitions

### Step 3: Configure TypeScript Paths

**Update `tsconfig.json`:**
```json
{
    "compilerOptions": {
        "paths": {
            "@/*": ["./resources/js/*"],
            "@/types": ["./resources/js/types/index.d.ts"]
        }
    }
}
```

### Step 4: Migrate One File at a Time

```typescript
// BEFORE (resources/js/pages/courses/Index.vue)
interface Course {
    id: number;
    title: string;
    // inline definition
}

// AFTER
import type { Course } from '@/types';
// Remove inline definition
```

### Step 5: Verify After Each Migration

```bash
# Run TypeScript check
npx tsc --noEmit

# Run existing tests (if any)
npm run test
```

### Rollback Procedure

If issues arise:
1. Revert the import statement
2. Restore inline interface definition
3. Types remain available for future migration

---

## Phase 2: Utilities Migration

### Step 1: Create Utility Files

Create all files as defined in [02-UTILITIES.md](./02-UTILITIES.md):
- `lib/formatters.ts`
- `lib/date.ts`
- `lib/string.ts`
- `lib/constants.ts`
- `lib/icons.ts`

### Step 2: Find All Duplicate Functions

```bash
# Search for duplicate formatDuration
grep -r "function formatDuration" resources/js/pages/ --include="*.vue"

# Search for duplicate difficultyLabel
grep -r "function difficultyLabel" resources/js/pages/ --include="*.vue"

# Search for all inline utility functions
grep -r "function format" resources/js/pages/ --include="*.vue"
```

### Step 3: Replace One Function at a Time

**Migration checklist per function:**
- [ ] Identify all files using the function
- [ ] Import from centralized utility
- [ ] Remove inline definition
- [ ] Test the page manually
- [ ] Commit changes

### Step 4: Verify Bundle Size

```bash
# Build and check bundle
npm run build

# Compare sizes before/after
```

### Rollback Procedure

If issues arise:
1. Restore inline function definition
2. Remove import statement
3. Both versions can coexist temporarily

---

## Phase 3: Component Migration

### Strategy: Strangler Fig Pattern

Replace components gradually by wrapping old components with new ones.

### Step 1: Create New Component Structure

```bash
mkdir -p resources/js/components/features/course
mkdir -p resources/js/components/features/lesson
mkdir -p resources/js/components/features/assessment
mkdir -p resources/js/components/features/shared
```

### Step 2: Start with Shared Components

Create in order:
1. `StatusBadge.vue` (simplest, used everywhere)
2. `EmptyState.vue` (common pattern)
3. `LoadingState.vue` (common pattern)

### Step 3: Use Feature Flags for Large Changes

**Feature Flag Composable:**
```typescript
// composables/features/useFeatureFlags.ts
import { computed } from 'vue';

interface FeatureFlags {
    newCourseForm: boolean;
    newLessonViewer: boolean;
    newAssessmentUI: boolean;
}

// Could be from server props or environment
const flags: FeatureFlags = {
    newCourseForm: import.meta.env.VITE_FF_NEW_COURSE_FORM === 'true',
    newLessonViewer: import.meta.env.VITE_FF_NEW_LESSON_VIEWER === 'true',
    newAssessmentUI: import.meta.env.VITE_FF_NEW_ASSESSMENT_UI === 'true',
};

export function useFeatureFlags() {
    return {
        isEnabled: (flag: keyof FeatureFlags) => flags[flag],
        flags: computed(() => flags),
    };
}
```

**Using Feature Flags:**
```vue
<script setup lang="ts">
import { useFeatureFlags } from '@/composables/features/useFeatureFlags';
import OldCourseForm from './OldCourseForm.vue';
import NewCourseForm from '@/components/features/course/CourseForm/index.vue';

const { isEnabled } = useFeatureFlags();
</script>

<template>
    <NewCourseForm v-if="isEnabled('newCourseForm')" v-bind="$attrs" />
    <OldCourseForm v-else v-bind="$attrs" />
</template>
```

### Step 4: Migrate One Page at a Time

**Migration order (by risk):**

1. **Low Risk** - Internal/admin pages
   - `admin/users/Index.vue`
   - `admin/courses/Index.vue`

2. **Medium Risk** - Secondary features
   - `courses/Index.vue`
   - `dashboard/Index.vue`

3. **High Risk** - Core user flows
   - `lessons/Show.vue`
   - `assessments/Take.vue`
   - `courses/Edit.vue`

### Step 5: A/B Testing (Optional)

For high-risk changes, use percentage rollout:

```typescript
// composables/features/usePercentageRollout.ts
export function usePercentageRollout(featureName: string, percentage: number) {
    // Use user ID or session ID for consistent experience
    const userId = usePage().props.auth.user?.id ?? 0;
    const hash = simpleHash(`${featureName}-${userId}`);
    const bucket = hash % 100;

    return bucket < percentage;
}

function simpleHash(str: string): number {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash);
}
```

### Rollback Procedure

1. Set feature flag to `false` in `.env`
2. Redeploy (no code changes needed)
3. Old component is immediately restored
4. Investigate and fix issues
5. Re-enable feature flag when ready

---

## Phase 4: Composables Migration

### Step 1: Extract Logic Without Changing Behavior

When extracting to composables:
1. Keep exact same functionality
2. Don't "improve" while migrating
3. Add tests before refactoring

### Step 2: Migration Pattern

```vue
<!-- BEFORE: Logic in component -->
<script setup lang="ts">
import { ref, computed } from 'vue';

const progress = ref(0);
const isCompleted = computed(() => progress.value >= 100);

function updateProgress(value: number) {
    progress.value = Math.max(progress.value, value);
}
</script>

<!-- AFTER: Logic in composable -->
<script setup lang="ts">
import { useProgressTracking } from '@/composables/features/useProgressTracking';

const { progress, isCompleted, updateProgress } = useProgressTracking(options);
</script>
```

### Step 3: Coexistence Period

Both approaches can work simultaneously:
- New pages use composables
- Old pages keep inline logic
- Migrate one page at a time

---

## Testing During Migration

### Pre-Migration Testing Checklist

Before migrating any component:
- [ ] Document current behavior
- [ ] Create smoke tests if none exist
- [ ] Take screenshots for visual comparison
- [ ] Note edge cases and error states

### Post-Migration Testing Checklist

After migrating:
- [ ] Manual smoke test
- [ ] Compare with screenshots
- [ ] Test all user interactions
- [ ] Check console for errors
- [ ] Verify network requests
- [ ] Test on mobile viewport

### Automated Testing

```bash
# Run all tests
npm run test

# Run tests for specific area
npm run test -- --grep "Course"

# Run with coverage
npm run test:coverage
```

---

## Rollback Procedures

### Quick Rollback (Feature Flags)

```bash
# Disable feature in .env
VITE_FF_NEW_COURSE_FORM=false

# Rebuild
npm run build

# Deploy
```

### Git Rollback (Last Resort)

```bash
# Find last working commit
git log --oneline

# Revert to specific commit
git revert <commit-hash>

# Or reset (destructive)
git reset --hard <commit-hash>

# Rebuild and deploy
npm run build
```

### Database Rollback

Frontend changes typically don't require database rollbacks. If API changes accompany frontend changes, coordinate with backend team.

---

## Team Onboarding

### Required Reading

1. [00-INDEX.md](./00-INDEX.md) - Overview and timeline
2. [01-TYPE-SYSTEM.md](./01-TYPE-SYSTEM.md) - Type definitions
3. [04-COMPOSABLES.md](./04-COMPOSABLES.md) - Composable patterns

### Code Style Guidelines

#### Component Structure
```vue
<script setup lang="ts">
// 1. Type imports
import type { Course } from '@/types';

// 2. Component imports
import StatusBadge from '@/components/features/shared/StatusBadge.vue';

// 3. Composable imports
import { useCourse } from '@/composables/data/useCourse';

// 4. Utility imports
import { formatDuration } from '@/lib/formatters';

// 5. Props and emits
interface Props {
    course: Course;
}
const props = defineProps<Props>();
const emit = defineEmits<{ select: [course: Course] }>();

// 6. Composables usage
const { updateLocal } = useCourse({ initial: props.course });

// 7. Local state
const isExpanded = ref(false);

// 8. Computed properties
const duration = computed(() => formatDuration(props.course.estimated_duration));

// 9. Methods
function handleClick() {
    emit('select', props.course);
}
</script>

<template>
    <!-- Template -->
</template>
```

#### File Naming
| Type | Convention | Example |
|------|------------|---------|
| Page | PascalCase | `Index.vue`, `Show.vue` |
| Component | PascalCase | `CourseCard.vue` |
| Composable | camelCase, `use` prefix | `useCourse.ts` |
| Utility | camelCase | `formatters.ts` |
| Type | camelCase | `course.ts` |

### Common Patterns

#### Importing Types
```typescript
// Good: Import type explicitly
import type { Course, Lesson } from '@/types';

// Good: Import multiple types
import type { Course, CourseStatus, DifficultyLevel } from '@/types';
```

#### Using Composables
```typescript
// Good: Destructure what you need
const { course, isLoading, fetch } = useCourse({ initial: props.course });

// Good: Rename if needed
const { isOpen: isModalOpen, open: openModal } = useModal();
```

#### Props and Emits
```typescript
// Good: Typed props
interface Props {
    course: Course;
    showActions?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
    showActions: true,
});

// Good: Typed emits
const emit = defineEmits<{
    select: [course: Course];
    delete: [id: number];
}>();
```

---

## Troubleshooting

### Common Issues

#### TypeScript Errors After Migration

**Issue:** Type errors after importing centralized types
**Solution:**
```typescript
// Check if the type definition matches usage
// You may need to add optional properties or update the type
```

#### Component Not Rendering

**Issue:** New component doesn't render
**Solution:**
1. Check browser console for errors
2. Verify all imports are correct
3. Check props are passed correctly
4. Verify feature flag is enabled

#### Performance Degradation

**Issue:** Page slower after migration
**Solution:**
1. Check for unnecessary re-renders
2. Verify lazy loading is working
3. Check bundle size with analyzer
4. Profile with Vue DevTools

### Getting Help

1. Check existing documentation in `/plans/front-end/`
2. Review similar components for patterns
3. Ask in team chat with specific details:
   - What you tried
   - Error messages
   - Component/file involved

---

## Success Metrics

Track these metrics throughout migration:

| Metric | Measure | Target |
|--------|---------|--------|
| Type Coverage | TypeScript strict check | 0 errors |
| Bundle Size | Production build | < 300KB initial |
| Test Coverage | Vitest coverage | > 70% |
| Component Size | Lines of code | < 200 lines avg |
| Page Load | Lighthouse | > 90 score |
| Bugs | Regressions | 0 critical |

---

## Timeline Summary

| Week | Phase | Deliverables |
|------|-------|--------------|
| 1-2 | Types | All type files created, 50% migrated |
| 2-3 | Utilities | All utility files, 100% migrated |
| 3-7 | Components | Shared components, course module |
| 7-10 | Composables | Core composables, integration |
| 10-12 | State | State patterns implemented |
| 12-15 | Testing | 70% coverage, CI integration |
| 15-17 | Performance | Optimization complete |
| Ongoing | Migration | Full migration complete |

---

## Final Checklist

### Before Declaring Migration Complete

- [ ] All inline types removed
- [ ] All duplicate utilities removed
- [ ] All monolithic components decomposed
- [ ] All business logic in composables
- [ ] Test coverage > 70%
- [ ] No TypeScript errors in strict mode
- [ ] Performance targets met
- [ ] Documentation updated
- [ ] Team trained on new patterns
