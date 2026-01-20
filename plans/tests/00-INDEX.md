# Enteraksi LMS - Comprehensive Integration Test Plan

## Executive Summary

This test plan provides comprehensive end-to-end integration test coverage for the Enteraksi Learning Management System. The plan is organized by user role and workflow, ensuring complete coverage of all application flows.

---

## Current Test Coverage Statistics

| Category | Existing Tests | Gaps Identified | Priority |
|----------|----------------|-----------------|----------|
| Authentication & Settings | 10 files | Minimal | Low |
| Course CRUD & State Machine | 3 files | Section/Lesson CRUD | Medium |
| Enrollment Lifecycle | 2 files | Re-enrollment, Completion w/Assessments | High |
| Lesson Progress | 3 files | Cross-section navigation | Low |
| Assessment Flow | 6 files | Required assessment blocking | High |
| Authorization/Policies | 2 unit files | Cross-role integration | High |
| End-to-End Journeys | 0 files | **All need creation** | Critical |

---

## Test Plan Documents

### [01 - Learner Journey](./01-learner-journey.md)
Complete learner experience from course discovery to completion:
- Course browsing and discovery
- Enrollment (public and invitation-based)
- Lesson viewing and progress tracking
- Assessment taking and grading
- Course completion and ratings
- Learner dashboard

### [02 - Instructor Journey](./02-instructor-journey.md)
Content Manager and Trainer workflows:
- Course creation and editing
- Section and lesson management
- Media upload and content management
- Assessment and question creation
- Student invitation (single and bulk)
- Grading workflows

### [03 - Admin & Cross-Role](./03-admin-cross-role.md)
LMS Admin capabilities and multi-role interactions:
- Admin-only capabilities (publish, archive, restore)
- Cross-role collaboration workflows
- Role-based view differences
- Data isolation between users

### [04 - Security & Authorization](./04-security-authorization.md)
Authorization boundary and security tests:
- Role escalation prevention
- Resource isolation
- Cross-resource validation
- Status-based restrictions

### [05 - Edge Cases & Data Integrity](./05-edge-cases.md)
Boundary conditions and data integrity:
- Numeric boundaries (0, max, overflow)
- State machine transitions
- Concurrent operations
- Data preservation on delete/archive

---

## Test File Organization

```
tests/
├── Feature/
│   ├── Auth/                          # Authentication (EXISTING)
│   ├── Settings/                      # User settings (EXISTING)
│   ├── Api/                           # API endpoints (EXISTING)
│   ├── Schema/                        # Database schema (EXISTING)
│   ├── Services/                      # Service tests (EXISTING)
│   │
│   ├── Journey/                       # NEW: End-to-end journeys
│   │   ├── LearnerCompleteJourneyTest.php
│   │   ├── InstructorCourseCreationTest.php
│   │   └── CrossRoleCollaborationTest.php
│   │
│   ├── Authorization/                 # NEW: Auth boundary tests
│   │   ├── RoleEscalationPreventionTest.php
│   │   ├── ResourceIsolationTest.php
│   │   └── StatusBasedRestrictionsTest.php
│   │
│   ├── ContentManagement/             # NEW: Section/Lesson CRUD
│   │   ├── SectionCrudTest.php
│   │   ├── LessonCrudTest.php
│   │   └── ContentReorderingTest.php
│   │
│   │── [Existing test files...]
│   │
│   └── EdgeCasesAndBusinessRulesTest.php  # Extend existing
│
└── Unit/
    ├── Domain/                        # Domain logic (EXISTING)
    └── Policies/                      # Policy tests (EXISTING)
```

---

## User Roles Reference

| Role | Code | Key Capabilities |
|------|------|------------------|
| Learner | `learner` | Browse, enroll, learn, take assessments |
| Content Manager | `content_manager` | Create/edit own courses, grade own assessments |
| Trainer | `trainer` | Same as content manager + invite to any course |
| LMS Admin | `lms_admin` | Full access: publish, archive, manage all |

---

## Test Helper Functions (Pest.php)

Available helpers for test implementation:

```php
asRole(string $role)                    // Create & auth user with role
asAdmin()                               // Quick admin auth
asContentManager()                      // Quick content manager auth
asLearner()                             // Quick learner auth
createPublishedCourseWithContent()      // Course + sections + lessons
createEnrolledLearner(?Course)          // User + course + enrollment
assertEventDispatched()                 // Event assertion
progressService()                       // Get progress service
```

---

## Implementation Priority

### Phase 1: Critical (Week 1-2)
1. End-to-end learner journey test
2. Required assessment blocking completion
3. Authorization boundary tests
4. Section/Lesson CRUD authorization

### Phase 2: High (Week 3-4)
5. Cross-role collaboration workflows
6. Trainer role comprehensive tests
7. Bulk invitation authorization
8. Re-enrollment after dropping

### Phase 3: Medium (Week 5-6)
9. Admin dashboard and metrics
10. Role-based view differences
11. Data isolation tests
12. API authorization parity

### Phase 4: Low (Week 7+)
13. Course browsing and filtering
14. Search functionality
15. Pagination edge cases
16. Performance under load

---

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/EnrollmentLifecycleTest.php

# Run tests by filter
php artisan test --filter=learner_can_enroll

# Run tests in parallel
php artisan test --parallel

# Run with coverage
php artisan test --coverage
```

---

## Test Patterns to Follow

### Naming Convention
```
test_[actor]_[can/cannot]_[action]_[context]
```

Examples:
- `test_learner_can_enroll_in_public_course`
- `test_content_manager_cannot_publish_course`
- `test_admin_can_restore_soft_deleted_course`

### Assertion Patterns
- `assertForbidden()` - Authorization failure (403)
- `assertNotFound()` - Resource not found (404)
- `assertRedirect()` - Successful redirect
- `assertDatabaseHas()` - Verify state change
- `assertSoftDeleted()` - Verify soft delete

---

## Key Findings

### Gaps Requiring Immediate Attention

1. **No E2E journey tests exist** - Critical gap for regression testing
2. **Section/Lesson CRUD not tested** - Content management authorization
3. **Trainer role undertested** - Only 2 explicit trainer tests
4. **Required assessment blocking** - `is_required` field not tested for completion
5. **Re-enrollment after drop** - Behavior undefined/untested

### Potential Policy Issues

1. **AssessmentPolicy::grade()** - Trainers may not be able to grade (only checks `isContentManager`)
2. **LearningPathPolicy** - Content managers CAN publish own learning paths (inconsistent with courses)

---

## Document Ownership

| Document | Last Updated | Maintainer |
|----------|--------------|------------|
| 00-INDEX.md | 2026-01-20 | Generated |
| 01-learner-journey.md | 2026-01-20 | Generated |
| 02-instructor-journey.md | 2026-01-20 | Generated |
| 03-admin-cross-role.md | 2026-01-20 | Generated |
| 04-security-authorization.md | 2026-01-20 | Generated |
| 05-edge-cases.md | 2026-01-20 | Generated |
