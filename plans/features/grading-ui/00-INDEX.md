# Grading UI Enhancement Plan

> **Status**: Planning
> **Priority**: High
> **Estimated Phases**: 3

---

## Executive Summary

Enhance the grading user experience by making the existing grading functionality discoverable and accessible through multiple entry points. Currently, the Grade page exists but is only accessible via direct URL - instructors have no navigation path to reach pending submissions.

---

## Current State Analysis

### What Exists ✅

| Component | Location | Status |
|-----------|----------|--------|
| Grade Page | `resources/js/pages/assessments/Grade.vue` | Complete |
| Grade Controller | `AssessmentController::grade()` | Complete |
| Grade Policy | `AssessmentAttemptPolicy::grade()` | Complete |
| Participant Card | `GradeParticipantCard.vue` | Complete |
| Answer Card | `GradeAnswerCard.vue` | Complete |
| Summary Card | `GradeSummaryCard.vue` | Complete |
| Tips Card | `GradeTipsCard.vue` | Complete |
| Stats Card | `GradeStatsCard.vue` | Complete |

### What's Missing ❌

| Feature | Impact |
|---------|--------|
| Dashboard widget | Instructors don't see pending submissions count |
| Sidebar/Navbar entry | No navigation to grading workflow |
| Attempts list page | No way to see all attempts needing grading |
| Contextual grade buttons | Must know direct URL to grade |

---

## User Stories

### Content Manager Stories

1. **US-G01**: As a content manager, I want to see how many submissions need grading on my dashboard, so I can prioritize my work.

2. **US-G02**: As a content manager, I want to view all assessment attempts for my courses, so I can manage grading efficiently.

3. **US-G03**: As a content manager, I want to filter attempts by status (pending, graded), so I can focus on what needs attention.

4. **US-G04**: As a content manager, I want to click directly to grade from the attempts list, so I can work efficiently.

### Admin Stories

5. **US-G05**: As an admin, I want to see system-wide pending grading count, so I can monitor instructor workload.

6. **US-G06**: As an admin, I want to view all attempts across all courses, so I can assist with grading if needed.

### Learner Stories

7. **US-G07**: As a learner, I want to see "Pending Review" status on my attempt, so I know it's awaiting grading.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         LAYER 1: DASHBOARD                       │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  PendingGradingCard.vue                                   │   │
│  │  - Shows count of pending submissions                     │   │
│  │  - Quick link to Attempts list                            │   │
│  │  - Visible to: CM, Admin                                  │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     LAYER 2: ATTEMPTS LIST                       │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  pages/assessments/Attempts.vue                           │   │
│  │  - Table of all attempts (filterable)                     │   │
│  │  - Columns: Learner, Assessment, Course, Status, Date     │   │
│  │  - Actions: View, Grade (if pending)                      │   │
│  │  - Sidebar navigation entry                               │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    LAYER 3: CONTEXTUAL BUTTONS                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  AttemptComplete.vue - Add "Grade" button                 │   │
│  │  AssessmentShow.vue - Add "View Attempts" link            │   │
│  │  - Visible based on policy authorization                  │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Considerations

### No New Tables Required

The existing schema already supports grading:

```sql
-- assessment_attempts table has:
-- - status: 'in_progress', 'submitted', 'graded', 'expired'
-- - score: nullable decimal for graded score
-- - graded_at: timestamp
-- - graded_by: user_id of grader
```

### New Query Requirements

```php
// Pending grading count (for dashboard widget)
AssessmentAttempt::where('status', 'submitted')
    ->whereHas('assessment', fn($q) => $q->where('user_id', $userId))
    ->count();

// All attempts for CM's courses
AssessmentAttempt::whereHas('assessment.course', fn($q) =>
    $q->where('user_id', $userId)
)->with(['user', 'assessment.course'])
->paginate();
```

---

## Implementation Phases

| Phase | Document | Description | Priority |
|-------|----------|-------------|----------|
| 1 | [01-ATTEMPTS-LIST.md](./01-ATTEMPTS-LIST.md) | Core attempts management page with filtering | High |
| 2 | [02-DASHBOARD-WIDGET.md](./02-DASHBOARD-WIDGET.md) | Pending grading dashboard card | Medium |
| 3 | [03-CONTEXTUAL-BUTTONS.md](./03-CONTEXTUAL-BUTTONS.md) | Grade buttons on existing pages | Low |

**Recommended Order**: Phase 1 → Phase 2 → Phase 3

Phase 1 is the foundation - the Attempts list page provides the core workflow. Dashboard widget and contextual buttons enhance discoverability but depend on Phase 1.

---

## API Endpoints

### New Endpoints

| Method | Route | Controller | Description |
|--------|-------|------------|-------------|
| GET | `/assessments/attempts` | `AssessmentAttemptController@index` | List all attempts |
| GET | `/api/grading/stats` | `GradingStatsController@index` | Dashboard widget data |

### Existing Endpoints (No Changes)

| Method | Route | Controller | Description |
|--------|-------|------------|-------------|
| GET | `/courses/{course}/assessments/{assessment}/attempts/{attempt}/grade` | `AssessmentController@grade` | Grade page |
| POST | `/courses/{course}/assessments/{assessment}/attempts/{attempt}/grade` | `AssessmentController@submitGrade` | Submit grade |

---

## Files to Create/Modify

### New Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/AssessmentAttemptController.php` | Attempts list controller |
| `resources/js/pages/assessments/Attempts.vue` | Attempts list page |
| `resources/js/components/dashboard/PendingGradingCard.vue` | Dashboard widget |
| `app/Http/Controllers/Api/GradingStatsController.php` | API for widget data |

### Modified Files

| File | Changes |
|------|---------|
| `resources/js/components/AppSidebar.vue` | Add "Grading" menu item |
| `resources/js/pages/Dashboard.vue` | Add PendingGradingCard |
| `resources/js/pages/assessments/AttemptComplete.vue` | Add Grade button |
| `routes/web.php` or `routes/assessments.php` | Add new routes |

---

## Success Criteria

### Functional Requirements

- [ ] CM can see pending grading count on dashboard
- [ ] CM can navigate to attempts list from sidebar
- [ ] CM can filter attempts by status, course, assessment
- [ ] CM can click to grade directly from attempts list
- [ ] Admin sees system-wide pending count
- [ ] Admin can view/grade any attempt
- [ ] Learner sees "Pending Review" on submitted attempts

### Non-Functional Requirements

- [ ] Attempts list loads < 500ms for 100 attempts
- [ ] Dashboard widget uses deferred props (no blocking)
- [ ] Mobile-responsive design
- [ ] Dark mode support
- [ ] Indonesian language for all UI text

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| N+1 queries on attempts list | Medium | High | Use eager loading with `with()` |
| Dashboard slowdown | Low | Medium | Use deferred props, cache stats |
| Permission gaps | Low | High | Comprehensive policy tests |

---

## Testing Strategy

### Unit Tests
- Policy tests for attempt viewing/grading authorization
- Stats calculation tests

### Feature Tests
- Attempts list endpoint authorization
- Filtering behavior
- Grade workflow integration

### Browser Tests (Optional)
- Full grading workflow from dashboard → grade → back

---

## Open Questions

1. Should we add email notifications when grading is complete?
2. Should we support bulk grading (grade multiple attempts at once)?
3. Should we add grading deadline reminders?

---

## References

- Existing Grade page: `resources/js/pages/assessments/Grade.vue`
- Assessment policy: `app/Policies/AssessmentAttemptPolicy.php`
- Similar list pattern: `resources/js/pages/courses/Index.vue`
