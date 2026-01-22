# Course Flow Fixes - Implementation Plan

**Created**: 2026-01-21
**Status**: ✅ PHASES 1-3 COMPLETE
**Current Phase**: Phase 3 Complete

---

## Overview

This plan addresses issues identified in the Course enrollment and progress flow analysis. Issues are categorized as:
- 🔧 **FIX** - Bug fix or correction to existing code
- 🆕 **NEW FEATURE** - Requires building new functionality (defer to future sprint)

---

## Phase Summary

| Phase | Focus | Status | Issues |
|-------|-------|--------|--------|
| Phase 1 | Critical Data Fixes | ✅ COMPLETE | started_at tracking, progress consistency |
| Phase 2 | Re-enrollment UX | ✅ COMPLETE | UI for dropped users, clear messaging |
| Phase 3 | Assessment Integration | ✅ COMPLETE | Clarify required assessments, optimize queries |
| Phase 4 | New Features | 📋 BACKLOG | Certificates, prerequisites, SCORM |

---

## Phase 1: Critical Data Fixes 🔧
**Goal**: Ensure enrollment data is accurate and complete

### Issue 1.1: Set `started_at` on First Content Access
**Type**: 🔧 FIX
**Priority**: HIGH
**Status**: ✅ COMPLETE

**Problem**: `Enrollment.started_at` field exists but is NEVER set. This prevents calculating:
- Time to completion
- Learner engagement metrics
- Course effectiveness analytics

**Solution**:
1. Set `started_at` when learner first accesses any lesson
2. Only set once (idempotent - don't overwrite if already set)
3. Add event for analytics tracking

**Files to Modify**:
- `app/Domain/Enrollment/Services/ProgressTrackingService.php`
- `app/Http/Controllers/LessonController.php` (alternative location)

**Implementation**:
```php
// In ProgressTrackingService::updateProgress() or LessonController::show()
if ($enrollment->started_at === null) {
    $enrollment->update(['started_at' => now()]);
    CourseStarted::dispatch($enrollment);
}
```

**Acceptance Criteria**:
- [ ] `started_at` is set on first lesson access
- [ ] `started_at` is NOT overwritten on subsequent accesses
- [ ] Event dispatched for tracking (optional)
- [ ] Tests verify the behavior

---

### Issue 1.2: Consistent Progress Completion Thresholds
**Type**: 🔧 FIX
**Priority**: MEDIUM
**Status**: ✅ COMPLETE

**Problem**: Inconsistent auto-completion thresholds:
- Media lessons: Complete at 90% watched
- Page lessons: Complete at 100% pages viewed
- This inconsistency may confuse learners

**Solution Options**:
A. Make threshold configurable per course (most flexible)
B. Standardize to 90% for both (easier)
C. Keep as-is but document clearly (least change)

**Recommended**: Option A - Configurable per course with sensible defaults

**Files to Modify**:
- `app/Models/Course.php` - Add `completion_threshold` field (or use config)
- `app/Domain/Enrollment/Services/ProgressTrackingService.php`
- Database migration if adding field

**Acceptance Criteria**:
- [ ] Threshold is configurable (course-level or global config)
- [ ] Default behavior preserved (90% media, 100% pages)
- [ ] Documentation updated

---

### Issue 1.3: Handle Deleted Lessons Mid-Progress
**Type**: 🔧 FIX
**Priority**: MEDIUM
**Status**: ✅ COMPLETE

**Problem**: If a lesson is deleted while learners have progress:
- Orphaned `LessonProgress` records remain
- Progress percentage may become incorrect
- Completed courses may become "incomplete"

**Solution**:
1. Soft delete lessons (already implemented)
2. On lesson soft delete, recalculate affected enrollments
3. Optionally: Mark orphaned progress as "legacy"

**Files to Modify**:
- `app/Domain/Course/Listeners/RecalculateProgressOnLessonDelete.php` (NEW)
- `app/Providers/EventServiceProvider.php`

**Acceptance Criteria**:
- [ ] Deleting a lesson recalculates active enrollment progress
- [ ] Completed enrollments are not affected (preserve completion)
- [ ] Orphaned progress records handled gracefully

---

## Phase 2: Re-enrollment UX 🔧
**Goal**: Make it easy for dropped users to resume learning

### Issue 2.1: Add Re-enrollment UI Flow
**Type**: 🔧 FIX
**Priority**: HIGH
**Status**: ✅ COMPLETE

**Problem**: Users who dropped a course have no clear way to resume:
- No "Resume Learning" button visible
- Service method exists but no controller endpoint
- UX doesn't distinguish new vs returning learners

**Solution**:
1. Add endpoint for re-enrollment
2. Show different UI for users with dropped enrollment
3. Ask user preference: "Start fresh" vs "Continue where you left off"

**Files to Modify**:
- `app/Http/Controllers/EnrollmentController.php` - Add `reenroll()` method
- `routes/web.php` - Add route
- `resources/js/Pages/courses/Detail.vue` - Update enrollment UI

**API Design**:
```php
// POST /courses/{course}/reenroll
public function reenroll(Course $course, Request $request)
{
    $preserveProgress = $request->boolean('preserve_progress', true);
    // ... call service
}
```

**Acceptance Criteria**:
- [ ] Dropped users see "Resume Learning" button
- [ ] Option to preserve or reset progress
- [ ] Clear messaging about what happens
- [ ] Tests for re-enrollment flow

---

### Issue 2.2: Show Enrollment Status on Course Card
**Type**: 🔧 FIX
**Priority**: LOW
**Status**: ✅ COMPLETE

**Problem**: Course browse page doesn't indicate enrollment status clearly:
- No badge for "In Progress"
- No badge for "Completed"
- No badge for "Dropped" (resume available)

**Solution**:
1. Pass enrollment status to browse page
2. Show appropriate badge on course cards
3. Different CTA based on status

**Files to Modify**:
- `app/Http/Controllers/CourseController.php` - Include enrollment data
- `resources/js/Components/courses/BrowseCourseCard.vue` - Add badges

**Acceptance Criteria**:
- [ ] Course cards show enrollment status
- [ ] Different CTA: "Enroll" / "Continue" / "Resume" / "Review"
- [ ] Visual distinction for completed courses

---

## Phase 3: Assessment Integration Clarity 🔧
**Goal**: Ensure assessments properly block/contribute to completion

### Issue 3.1: Clarify Required Assessment Logic
**Type**: 🔧 FIX
**Priority**: HIGH
**Status**: ✅ COMPLETE

**Problem**: `AssessmentInclusiveProgressCalculator` exists but:
- Unclear which assessments are "required" (`is_required` field exists)
- Query pattern may have N+1 issues
- Not clear if partial credit supported

**Solution**:
1. Audit `Assessment.is_required` usage
2. Document the completion logic clearly
3. Optimize queries (eager load attempts)
4. Add tests for edge cases

**Files to Review/Modify**:
- `app/Domain/Progress/Calculators/AssessmentInclusiveProgressCalculator.php`
- `app/Models/Assessment.php`
- `app/Models/AssessmentAttempt.php`

**Acceptance Criteria**:
- [ ] Clear documentation of what "required" means
- [ ] Queries optimized (no N+1)
- [ ] Edge cases tested (no attempts, failed attempts, retakes)
- [ ] Progress calculation is consistent

---

### Issue 3.2: Assessment Progress Visibility
**Type**: 🔧 FIX
**Priority**: MEDIUM
**Status**: ✅ COMPLETE

**Problem**: Learners may not see why they're not at 100%:
- Progress bar shows lesson progress only
- No indication of pending assessments
- Confusing when all lessons done but course not complete

**Solution**:
1. Include assessment status in progress response
2. Show "X assessments remaining" in UI
3. Distinguish lesson vs assessment progress

**Files to Modify**:
- `app/Domain/Enrollment/DTOs/ProgressResult.php` - Add assessment stats
- `app/Domain/Enrollment/Services/ProgressTrackingService.php`
- Frontend progress components

**Acceptance Criteria**:
- [ ] DTO includes assessment completion stats
- [ ] UI shows pending assessments
- [ ] Clear path to 100% completion

---

## Phase 4: New Features 🆕 (BACKLOG)
**Goal**: Add industry-standard capabilities

> **NOTE**: These are NEW FEATURES requiring separate planning and implementation.
> Document here for future sprint planning.

---

### Feature 4.1: Certificate Generation
**Type**: 🆕 NEW FEATURE
**Priority**: HIGH (Business requirement for banking compliance)
**Status**: 📋 BACKLOG

**Scope**:
- Generate PDF certificate on course completion
- Include: learner name, course title, completion date, unique certificate ID
- Store certificate record in database
- Allow learner to download/view certificates
- Admin can configure certificate template per course

**Related Events**: `EnrollmentCompleted`

**Estimated Effort**: Medium (2-3 days)

**Dependencies**:
- PDF generation library (dompdf or similar)
- Certificate template design
- Certificate storage strategy
- Certificate model and migration

**Database Schema**:
```sql
certificates:
  - id
  - enrollment_id (FK)
  - certificate_number (unique)
  - issued_at
  - expires_at (nullable, for recertification)
  - pdf_path
  - metadata (JSON)
```

---

### Feature 4.2: Lesson Prerequisites
**Type**: 🆕 NEW FEATURE
**Priority**: MEDIUM
**Status**: 📋 BACKLOG

**Scope**:
- Define prerequisites between lessons
- Lock lessons until prerequisites completed
- Show lock icon and reason in UI
- Support sequential mode (like learning paths)

**Estimated Effort**: Medium (2-3 days)

**Note**: Similar to LearningPath prerequisite logic - can reuse patterns

---

### Feature 4.3: Min Completion Percentage for Courses
**Type**: 🆕 NEW FEATURE
**Priority**: MEDIUM
**Status**: 📋 BACKLOG

**Scope**:
- Add `min_completion_percentage` field to courses
- Allow completion at <100% if threshold met
- Useful for optional content sections

**Estimated Effort**: Small (1 day)

**Note**: Already exists in LearningPathCourse pivot - extend pattern

---

### Feature 4.4: Time-Based Content Unlock (Drip Content)
**Type**: 🆕 NEW FEATURE
**Priority**: LOW
**Status**: 📋 BACKLOG

**Scope**:
- Schedule lesson availability (unlock after X days from enrollment)
- Show countdown/date for locked content
- Useful for paced learning programs

**Estimated Effort**: Medium (2-3 days)

---

### Feature 4.5: SCORM/xAPI Integration
**Type**: 🆕 NEW FEATURE
**Priority**: LOW (Future consideration)
**Status**: 📋 BACKLOG

**Scope**:
- SCORM 1.2/2004 package upload and playback
- xAPI statement tracking
- LRS (Learning Record Store) integration

**Estimated Effort**: Large (1-2 weeks)

**Note**: May not be needed if all content is internal. Evaluate business need first.

---

### Feature 4.6: Video Captions/Transcripts
**Type**: 🆕 NEW FEATURE
**Priority**: LOW (Accessibility)
**Status**: 📋 BACKLOG

**Scope**:
- Upload VTT/SRT caption files for videos
- Display captions in video player
- Store transcript text for search/accessibility

**Estimated Effort**: Medium (2-3 days)

---

## Implementation Progress Tracking

### Phase 1 Progress ✅ COMPLETE
- [x] 1.1 Set `started_at` on first access
  - [x] Modified ProgressTrackingService.markCourseStartedIfNeeded()
  - [x] Created CourseStarted event with metadata
  - [x] Tests: CourseStartedTrackingTest.php (6 tests)
- [x] 1.2 Consistent completion thresholds
  - [x] Added config/lms.php completion_thresholds
  - [x] Media: 90% default (configurable)
  - [x] Pages: 100% default (configurable)
  - [x] Tests: CompletionThresholdTest.php (5 tests)
- [x] 1.3 Handle deleted lessons
  - [x] Created LessonDeleted event
  - [x] Created RecalculateProgressOnLessonDeletion listener
  - [x] Fixed calculators to exclude soft-deleted lessons
  - [x] Tests: DeletedLessonsHandlingTest.php (7 tests)

### Phase 2 Progress ✅ COMPLETE
- [x] 2.1 Re-enrollment UI
  - [x] Add `/courses/{course}/reenroll` endpoint
  - [x] Update `CourseEnrollmentCard.vue` with 4 states (active, completed, dropped, not enrolled)
  - [x] Add dialog for preserve/reset progress choice
  - [x] Tests: ReenrollmentFlowTest.php (8 tests)
- [x] 2.2 Enrollment status badges
  - [x] Pass enrollmentMap to browse page in CourseController
  - [x] Update BrowseCourseCard with status badges and progress bars
  - [x] Dynamic CTAs based on enrollment status

### Phase 3 Progress ✅ COMPLETE
- [x] 3.1 Assessment integration clarity
  - [x] Fixed N+1 queries in AssessmentInclusiveProgressCalculator (batch loading)
  - [x] Made progress calculation consistent (only counts required assessments)
  - [x] Added comprehensive PHPDoc documentation
  - [x] Tests: 19 tests covering edge cases (no attempts, failed attempts, retakes)
- [x] 3.2 Assessment progress visibility
  - [x] Created AssessmentStats value object
  - [x] Updated ProgressResult DTO with optional assessment stats
  - [x] Added getAssessmentStats() to ProgressTrackingService
  - [x] Updated CourseEnrollmentCard to show pending assessments

---

## Notes for Context Preservation

**When conversation is compacted, maintain these states**:
1. Current phase being worked on
2. Completed issues (marked with ✅)
3. In-progress issue and what step we're on
4. Any blockers or decisions made

**Key Decisions Pending**:
- Completion threshold: Config vs DB field vs keep as-is?
- Re-enrollment: Modal dialog or separate page?
- Assessment: How to show progress breakdown?

---

## Change Log

| Date | Phase | Change |
|------|-------|--------|
| 2026-01-21 | Plan | Initial plan created |
| 2026-01-21 | Phase 1 | 1.1 started_at tracking implemented |
| 2026-01-21 | Phase 1 | 1.2 Configurable completion thresholds implemented |
| 2026-01-21 | Phase 1 | 1.3 Deleted lessons handling implemented |
| 2026-01-21 | Phase 1 | Phase 1 COMPLETE - 18 tests added |
| 2026-01-21 | Phase 2 | 2.1 Re-enrollment UI Flow implemented |
| 2026-01-21 | Phase 2 | 2.2 Enrollment status badges implemented |
| 2026-01-21 | Phase 2 | Phase 2 COMPLETE - 8 tests added |
| 2026-01-21 | Phase 3 | 3.1 Fixed N+1 queries, consistent required assessment logic |
| 2026-01-21 | Phase 3 | 3.2 Assessment stats visibility in UI |
| 2026-01-21 | Phase 3 | Phase 3 COMPLETE - 8 new edge case tests added |
