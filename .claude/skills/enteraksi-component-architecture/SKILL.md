---
name: enteraksi-component-architecture
description: Component extraction patterns, page refactoring guidelines, and architecture conventions for Enteraksi LMS Vue components. Use when refactoring pages over 200 lines, extracting reusable components, or consolidating duplicate patterns.
triggers:
  - refactor component
  - extract component
  - page too long
  - reduce page lines
  - component architecture
  - consolidate components
  - vue component patterns
---

# Enteraksi Component Architecture

## When to Use This Skill

- Refactoring Vue pages over 200 lines
- Extracting reusable components from pages
- Consolidating duplicate sidebar/form patterns
- Creating shared components for Create/Edit pages
- Optimizing component organization

## Key Patterns

### 1. Shared Form Sidebar (Create/Edit)

Use configurable labels instead of separate components:

```vue
<AssessmentFormSidebar
    v-model:status="form.status"
    v-model:visibility="form.visibility"
    :cancel-href="cancelUrl"
    :processing="processing"
    :errors="{ status: errors.status, visibility: errors.visibility }"
    submit-label="Simpan Penilaian"  <!-- or "Simpan Perubahan" for edit -->
/>
```

### 2. Reusable Toggle Option

For switch/toggle patterns with icon, title, description:

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

### 3. Info Card Pattern

For sidebar cards with icon-label pairs:

```vue
<AttemptInfoCard
    :attempt-number="attempt.attempt_number"
    :time-elapsed="timeElapsed"
    :time-left="timeLeft"
    :has-time-limit="!!assessment.time_limit_minutes"
    :passing-score="assessment.passing_score"
    :total-questions="assessment.questions.length"
/>
```

## Component Naming

```
{Domain}{Context}{Type}.vue

Types: Card, Sidebar, Form, List, Header
Examples:
- AssessmentFormSidebar.vue
- AttemptInfoCard.vue
- LearningPathCourseCard.vue
```

## Target Metrics

| Type | Target | Max |
|------|--------|-----|
| Page | < 200 lines | 250 lines |
| Component | < 150 lines | 200 lines |

## Gotchas

1. **Keep types in pages** - ~30-50 lines of page-specific types is acceptable
2. **Use defineModel for two-way binding** - Named models for multiple v-model props
3. **Map errors explicitly** - Don't pass entire errors object, map specific fields
4. **Icons as props** - Use `Component` type from Vue for icon props

## Existing Components

### Assessment Components
- `AssessmentFormSidebar.vue` - Shared create/edit sidebar
- `AssessmentToggleOption.vue` - Toggle with icon/title/desc
- `AttemptInfoCard.vue` - Attempt stats display
- `AttemptNavigationCard.vue` - Question navigation grid
- `AttemptTipsCard.vue` - Tips display

### Course Components
- `BrowseCourseCard.vue` - Course card for browsing
- `CourseContentOutline.vue` - Curriculum accordion
- `CourseEnrollmentCard.vue` - Enrollment CTA
- `CourseMetaCard.vue` - Course metadata

### Learning Path Components
- `LearningPathCourseCard.vue` - Course in learning path

## Quick Check Commands

```bash
# Check page line counts
wc -l resources/js/pages/**/*.vue | sort -n | tail -20

# Build verification
npm run build
```

## Full Documentation

See `/Users/satriyo/dev/laravel-project/enteraksi/.claude/skills/enteraksi-component-architecture.md` for complete patterns, examples, and refactoring checklist.
