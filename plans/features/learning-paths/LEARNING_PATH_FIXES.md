# Learning Path Flow Fixes - Implementation Plan

**Created**: 2026-01-21
**Completed**: 2026-01-21
**Status**: ✅ COMPLETED (Phases 1-3)
**Current Phase**: Phase 4 (BACKLOG - New Features)

---

## Overview

This plan addresses critical issues identified in the Learning Path learner flow analysis. Issues are categorized as:
- 🔧 **FIX** - Bug fix or correction to existing code
- 🆕 **NEW FEATURE** - Requires building new functionality (defer to future sprint)

---

## Phase Summary

| Phase | Focus | Status | Issues |
|-------|-------|--------|--------|
| Phase 1 | Critical Flow Fixes | ✅ COMPLETED | Auto-enrollment, Enrollment linking |
| Phase 2 | Data Integrity | ✅ COMPLETED | Sync states, Re-enrollment |
| Phase 3 | UX Consistency | ✅ COMPLETED | Progress calculation |
| Phase 4 | New Features | 📋 BACKLOG | Certificates, Transcript, SCORM |

---

## Phase 1: Critical Flow Fixes 🔧
**Goal**: Make the core enrollment → course access flow work correctly

### Issue 1.1: Auto-Enroll in Courses on Path Enrollment
**Type**: 🔧 FIX
**Priority**: CRITICAL
**Status**: ✅ COMPLETED

**Problem**: When learner enrolls in a learning path, no `Enrollment` records are created for courses. Learner cannot access course content.

**Solution**:
1. Modify `PathEnrollmentService::initializeCourseProgress()` to also create `Enrollment` records for available courses
2. Link `course_enrollment_id` in `LearningPathCourseProgress`
3. When next courses are unlocked, auto-create their `Enrollment` records

**Files Modified**:
- `app/Domain/LearningPath/Services/PathEnrollmentService.php` - Added `ensureCourseEnrollment()` method
- `app/Domain/LearningPath/Services/PathProgressService.php` - Modified `unlockCourse()` to create enrollment

**Acceptance Criteria**:
- [x] When path enrollment created, first available course(s) have Enrollment records
- [x] `course_enrollment_id` is populated in LearningPathCourseProgress
- [x] When course is unlocked, Enrollment is auto-created
- [x] Tests pass for enrollment flow

---

### Issue 1.2: Ensure Enrollment-Progress Linking
**Type**: 🔧 FIX
**Priority**: CRITICAL
**Status**: ✅ COMPLETED

**Problem**: `LearningPathCourseProgress.course_enrollment_id` can be null, breaking the link to actual course progress.

**Solution**:
1. Make `course_enrollment_id` required for non-locked courses
2. Add validation in state transitions
3. Update existing records if needed (data migration)

**Files Modified**:
- `app/Domain/LearningPath/Services/PathEnrollmentService.php` - Creates enrollment on path enrollment
- `app/Domain/LearningPath/Services/PathProgressService.php` - Links enrollment on unlock

**Acceptance Criteria**:
- [x] Available/InProgress/Completed states always have course_enrollment_id
- [x] Locked state can have null (no enrollment yet)
- [x] Tests verify linking behavior

---

## Phase 2: Data Integrity Fixes 🔧
**Goal**: Ensure data consistency between course enrollments and path progress

### Issue 2.1: Sync Course Enrollment State to Path Progress
**Type**: 🔧 FIX
**Priority**: HIGH
**Status**: ✅ COMPLETED

**Problem**: If learner drops a course enrollment, the `LearningPathCourseProgress` is not updated. Path shows incorrect completion state.

**Solution**:
1. Create listener for `UserDropped` event
2. Update related `LearningPathCourseProgress` when course is dropped
3. Re-lock dependent courses if prerequisites no longer met
4. Recalculate path progress percentage

**Files Created/Modified**:
- `app/Domain/LearningPath/Listeners/UpdatePathProgressOnCourseDrop.php` - NEW listener
- `app/Providers/EventServiceProvider.php` - Registered listener for UserDropped event
- `tests/Unit/Domain/LearningPath/Services/PathProgressServiceTest.php` - Added tests

**Acceptance Criteria**:
- [x] Dropping course enrollment updates path progress
- [x] Course reverts to available state (not re-locked for simplicity)
- [x] Progress percentage recalculated
- [x] Path state reverts from completed to active if needed

---

### Issue 2.2: Handle Re-enrollment After Drop
**Type**: 🔧 FIX
**Priority**: HIGH
**Status**: ✅ COMPLETED

**Problem**: Unique constraint on `(user_id, learning_path_id)` prevents re-enrollment after drop.

**Solution Implemented**: Option C - Update existing dropped record
- Added `getDroppedEnrollment()` method to find existing dropped enrollment
- Added `reactivateEnrollment()` method with `preserveProgress` option
- Modified `enroll()` to check for and reactivate dropped enrollments
- Updated `canEnroll()` to return true for users with dropped enrollments

**Files Modified**:
- `app/Domain/LearningPath/Services/PathEnrollmentService.php` - Added re-enrollment logic
- `app/Domain/LearningPath/DTOs/PathEnrollmentResult.php` - Added `message` field
- `tests/Unit/Domain/LearningPath/Services/PathEnrollmentServiceTest.php` - Added tests

**Acceptance Criteria**:
- [x] Learner can re-enroll after dropping
- [x] Previous progress reset by default, preserved if `preserveProgress: true`
- [x] `isNewEnrollment: false` indicates reactivation
- [x] Message field in result indicates re-enrollment status

---

## Phase 3: UX Consistency Fixes 🔧
**Goal**: Fix display and calculation inconsistencies

### Issue 3.1: Progress Calculation - Required vs Optional Courses
**Type**: 🔧 FIX
**Priority**: MEDIUM
**Status**: ✅ COMPLETED

**Problem**: `calculateProgressPercentage()` counts all courses, but `isPathCompleted()` only counts required courses. UI shows misleading progress.

**Solution Implemented**:
1. Updated `calculateProgressPercentage()` to only count required courses
2. Added `requiredCourses`, `completedRequiredCourses`, `requiredPercentage` to PathProgressResult DTO
3. Updated `getProgress()` to populate both overall and required progress stats
4. If no required courses defined, all courses are considered required (backwards compatible)

**Files Modified**:
- `app/Domain/LearningPath/Services/PathProgressService.php` - Updated calculation logic
- `app/Domain/LearningPath/DTOs/PathProgressResult.php` - Added required course fields
- `tests/Unit/Domain/LearningPath/Services/PathProgressServiceTest.php` - Added tests

**Acceptance Criteria**:
- [x] Progress % matches completion logic (required courses only)
- [x] DTO exposes both overall and required progress stats
- [x] 100% shown when all required courses done (even if optional remain)
- [x] Backwards compatible (no required = all courses count)

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
- Generate PDF certificate on path completion
- Include: learner name, path title, completion date, unique certificate ID
- Store certificate record in database
- Allow learner to download/view certificates
- Admin can configure certificate template

**Related Events**: `PathCompleted`

**Estimated Effort**: Medium (2-3 days)

**Dependencies**:
- PDF generation library (dompdf or similar)
- Certificate template design
- Certificate storage strategy

---

### Feature 4.2: Learner Transcript
**Type**: 🆕 NEW FEATURE
**Priority**: MEDIUM
**Status**: 📋 BACKLOG

**Scope**:
- Permanent record of all completed courses and paths
- Include dates, scores, certificates earned
- Exportable as PDF
- Viewable by learner and admin

**Estimated Effort**: Medium (2-3 days)

---

### Feature 4.3: SCORM/xAPI Integration
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

### Feature 4.4: LTI Integration
**Type**: 🆕 NEW FEATURE
**Priority**: LOW (Future consideration)
**Status**: 📋 BACKLOG

**Scope**:
- LTI 1.3 consumer implementation
- Embed external LMS content
- Grade passback

**Estimated Effort**: Large (1-2 weeks)

---

### Feature 4.5: Mastery-Based Prerequisites
**Type**: 🆕 NEW FEATURE
**Priority**: LOW
**Status**: 📋 BACKLOG

**Scope**:
- Unlock courses based on competency/skill achievement
- Not just position-based but score-based
- Use `min_completion_percentage` from pivot table

**Estimated Effort**: Small (1 day)

**Note**: Partially exists - `min_completion_percentage` field exists but not used in prerequisite evaluation.

---

## Implementation Progress Tracking

### Phase 1 Progress ✅
- [x] 1.1 Auto-enroll in courses
  - [x] Modify PathEnrollmentService - Added `ensureCourseEnrollment()`
  - [x] Modify PathProgressService for unlock - Updated `unlockCourse()`
  - [x] Write tests - 10+ new test cases
- [x] 1.2 Enrollment-Progress linking
  - [x] Link course_enrollment_id on enrollment and unlock
  - [x] Write tests - Verified linking behavior

### Phase 2 Progress ✅
- [x] 2.1 Sync on course drop
  - [x] Create listener - `UpdatePathProgressOnCourseDrop`
  - [x] Register event - Added to EventServiceProvider
  - [x] Write tests - 3 test cases
- [x] 2.2 Re-enrollment handling
  - [x] Update enroll logic - Added reactivation flow
  - [x] Write tests - 3 test cases for reset/preserve progress

### Phase 3 Progress ✅
- [x] 3.1 Progress calculation fix
  - [x] Update service - `calculateProgressPercentage()` uses required courses
  - [x] Update DTO - Added requiredCourses, completedRequiredCourses, requiredPercentage
  - [x] Write tests - 3 test cases for required vs optional

---

## Notes for Context Preservation

**When conversation is compacted, maintain these states**:
1. Current phase being worked on
2. Completed issues (marked with ✅)
3. In-progress issue and what step we're on
4. Any blockers or decisions made

**Key Decisions Made**:
- Re-enrollment: Use Option C (update existing dropped record)
- Progress calculation: Required courses only for main %
- Certificate: Deferred to Phase 4 as new feature

---

## Change Log

| Date | Phase | Change |
|------|-------|--------|
| 2026-01-21 | Plan | Initial plan created |
| 2026-01-21 | Phase 1 | ✅ Completed auto-enrollment and enrollment linking |
| 2026-01-21 | Phase 2 | ✅ Completed course drop sync and re-enrollment handling |
| 2026-01-21 | Phase 3 | ✅ Completed progress calculation fix for required vs optional |
| 2026-01-21 | All | All fix phases (1-3) completed. 65+ tests passing. |
