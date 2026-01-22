# Phase 3: Contextual Buttons

> **Priority**: Low
> **Dependencies**: Phase 1 (Grade route must exist)
> **Estimated Effort**: Low

---

## Objectives

1. Add "Grade" button on AttemptComplete page for instructors
2. Add "View Attempts" link on Assessment detail page
3. Show "Pending Review" status badge for learners
4. All buttons respect authorization policies

---

## User Stories Addressed

- **US-G04**: Click directly to grade from attempt view
- **US-G07**: Learner sees "Pending Review" on submitted attempts

---

## Implementation Details

### 1. AttemptComplete Page - Add Grade Button

The AttemptComplete page shows after a learner submits an assessment. For instructors viewing this page, add a "Grade" button.

```vue
<!-- resources/js/pages/assessments/AttemptComplete.vue -->
<!-- Add to script setup -->

<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

interface Props {
    course: Course
    assessment: Assessment
    attempt: Attempt
    canGrade: boolean  // NEW: passed from controller
}

const props = defineProps<Props>()
</script>

<!-- Add to template, near existing action buttons -->
<template>
    <!-- ... existing content ... -->

    <!-- Grade Button for Instructors -->
    <div v-if="canGrade && attempt.status === 'submitted'" class="mt-6">
        <Link
            :href="route('assessments.grade', [course.id, assessment.id, attempt.id])"
        >
            <Button>
                Nilai Submission Ini
            </Button>
        </Link>
    </div>

    <!-- Status for Learner -->
    <div v-else-if="attempt.status === 'submitted'" class="mt-6">
        <Badge variant="warning">
            Menunggu Penilaian
        </Badge>
        <p class="text-sm text-muted-foreground mt-2">
            Submission Anda sedang ditinjau oleh instruktur.
        </p>
    </div>
</template>
```

### 2. Controller Update - Pass canGrade Flag

```php
// app/Http/Controllers/AssessmentController.php
// Update the attemptComplete method

public function attemptComplete(Course $course, Assessment $assessment, AssessmentAttempt $attempt): Response
{
    Gate::authorize('viewAttempt', [$attempt, $assessment, $course]);

    // Check if current user can grade this attempt
    $canGrade = Gate::allows('grade', [$attempt, $assessment, $course]);

    return Inertia::render('assessments/AttemptComplete', [
        'course' => $course,
        'assessment' => $assessment,
        'attempt' => $attempt->load('answers.question'),
        'canGrade' => $canGrade,  // NEW
    ]);
}
```

### 3. Assessment Show Page - Add Attempts Link

For instructors viewing an assessment, show a link to see all attempts.

```vue
<!-- resources/js/pages/assessments/Show.vue -->
<!-- Add to script setup -->

<script setup lang="ts">
interface Props {
    course: Course
    assessment: Assessment
    canManage: boolean
    pendingAttemptsCount: number  // NEW: count of pending attempts
}

const props = defineProps<Props>()
</script>

<!-- Add to template, in the actions area -->
<template>
    <!-- Existing content... -->

    <!-- Attempts Link for Instructors -->
    <div v-if="canManage" class="flex items-center gap-4">
        <Link
            :href="route('assessments.attempts.index', { assessment_id: assessment.id })"
        >
            <Button variant="outline">
                Lihat Submission
                <Badge v-if="pendingAttemptsCount > 0" variant="warning" class="ml-2">
                    {{ pendingAttemptsCount }}
                </Badge>
            </Button>
        </Link>
    </div>
</template>
```

### 4. Controller Update - Pass Pending Count

```php
// app/Http/Controllers/AssessmentController.php
// Update the show method

public function show(Course $course, Assessment $assessment): Response
{
    $user = Auth::user();
    $canManage = $user->isLmsAdmin() || $assessment->user_id === $user->id;

    $pendingAttemptsCount = 0;
    if ($canManage) {
        $pendingAttemptsCount = $assessment->attempts()
            ->where('status', 'submitted')
            ->count();
    }

    return Inertia::render('assessments/Show', [
        'course' => $course,
        'assessment' => $assessment,
        'canManage' => $canManage,
        'pendingAttemptsCount' => $pendingAttemptsCount,  // NEW
    ]);
}
```

### 5. Course Detail Page - Grading Summary (Optional)

Add a small summary on course detail for instructors.

```vue
<!-- resources/js/pages/courses/Detail.vue or Show.vue -->
<!-- Add for instructors -->

<template>
    <!-- In the course info section for instructors -->
    <Card v-if="canManage && gradingStats" class="mt-4">
        <CardHeader>
            <CardTitle class="text-sm">Penilaian</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="flex items-center gap-4">
                <div>
                    <span class="text-2xl font-bold">{{ gradingStats.pending }}</span>
                    <span class="text-sm text-muted-foreground"> tertunda</span>
                </div>
                <Link :href="route('assessments.attempts.index', { course_id: course.id })">
                    <Button variant="link" size="sm">Lihat →</Button>
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
```

---

## Implementation Checklist

### AttemptComplete Page

- [ ] Add `canGrade` prop from controller
- [ ] Add "Nilai Submission Ini" button when canGrade && status === 'submitted'
- [ ] Add "Menunggu Penilaian" badge for learners
- [ ] Test button only appears for authorized users

### Assessment Show Page

- [ ] Add `pendingAttemptsCount` prop from controller
- [ ] Add "Lihat Submission" button with badge
- [ ] Link to attempts list filtered by assessment_id
- [ ] Test count is accurate

### Course Detail Page (Optional)

- [ ] Add grading summary card for instructors
- [ ] Show pending count for course
- [ ] Link to attempts list filtered by course_id

### Testing

- [ ] Test learner does NOT see grade button
- [ ] Test CM sees grade button on own course attempts
- [ ] Test Admin sees grade button on any attempt
- [ ] Test pending count is accurate
- [ ] Test links navigate correctly

---

## Visual Summary

```
┌─────────────────────────────────────────────────────────────────┐
│  LEARNER VIEW - AttemptComplete                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Terima kasih telah menyelesaikan assessment!             │   │
│  │                                                           │   │
│  │  [🟡 Menunggu Penilaian]                                  │   │
│  │  Submission Anda sedang ditinjau oleh instruktur.         │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  INSTRUCTOR VIEW - AttemptComplete                               │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Submission dari John Doe                                 │   │
│  │                                                           │   │
│  │  [Nilai Submission Ini]                                   │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  INSTRUCTOR VIEW - Assessment Show                               │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Quiz Bab 1: Pengenalan Laravel                           │   │
│  │                                                           │   │
│  │  [Edit] [Lihat Submission 🟡 3]                           │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Indonesian Labels Reference

| English | Indonesian |
|---------|------------|
| Grade This Submission | Nilai Submission Ini |
| Pending Review | Menunggu Penilaian |
| Your submission is being reviewed | Submission Anda sedang ditinjau |
| View Submissions | Lihat Submission |
| pending | tertunda |
| Grading | Penilaian |

---

## Related Files

- AttemptComplete page: `resources/js/pages/assessments/AttemptComplete.vue`
- Assessment Show page: `resources/js/pages/assessments/Show.vue`
- Course Detail page: `resources/js/pages/courses/Detail.vue`
- Grade page (existing): `resources/js/pages/assessments/Grade.vue`
