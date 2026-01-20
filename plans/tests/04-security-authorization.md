# 04 - Security & Authorization Test Plan

## Overview

This document covers authorization boundary testing, security tests, and negative test cases to ensure proper access control throughout the application.

---

## 1. Role Escalation Prevention

### 1.1 Learner Cannot Access Management

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_cannot_access_course_management` | GET /courses/create | 403 Forbidden | **NEEDS TEST** |
| `test_learner_cannot_publish_course` | POST publish | 403 Forbidden | **NEEDS TEST** |
| `test_learner_cannot_access_admin_dashboard` | GET /dashboard | Redirect to learner | **NEEDS TEST** |
| `test_learner_cannot_create_assessment` | POST assessment | 403 Forbidden | **NEEDS TEST** |
| `test_learner_cannot_grade_attempt` | POST grade | 403 Forbidden | EXISTS |
| `test_learner_cannot_invite_users` | POST invitation | 403 Forbidden | **NEEDS TEST** |
| `test_learner_cannot_upload_media` | POST media | 403 Forbidden | EXISTS |

### 1.2 Content Manager Cannot Publish

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cm_cannot_publish_via_route` | POST publish | 403 Forbidden | EXISTS |
| `test_cm_cannot_publish_via_api` | API POST | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_archive_course` | POST archive | 403 Forbidden | EXISTS |
| `test_cm_cannot_set_status_directly` | PATCH status | 403 Forbidden | EXISTS |
| `test_cm_cannot_set_visibility` | PATCH visibility | 403 Forbidden | EXISTS |
| `test_cm_cannot_publish_assessment` | POST publish | 403 Forbidden | EXISTS |

### 1.3 Trainer Restrictions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_trainer_cannot_publish_course` | POST publish | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_unpublish_course` | POST unpublish | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_archive_course` | POST archive | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_set_status` | PATCH status | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_publish_assessment` | POST publish | 403 Forbidden | **NEEDS TEST** |

### 1.4 Guest Access Prevention

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_guest_cannot_access_dashboard` | GET /dashboard | Redirect login | **NEEDS TEST** |
| `test_guest_cannot_create_course` | POST courses | Redirect login | **NEEDS TEST** |
| `test_guest_cannot_enroll` | POST enroll | Redirect login | EXISTS |
| `test_guest_cannot_view_lessons` | GET lesson | Redirect login | EXISTS |
| `test_guest_cannot_start_assessment` | POST start | Redirect login | EXISTS |

---

## 2. Resource Isolation

### 2.1 Course Isolation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cm_cannot_update_others_course` | PATCH other's | 403 Forbidden | EXISTS |
| `test_cm_cannot_delete_others_course` | DELETE other's | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_view_others_draft` | GET draft | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_add_section_to_others` | POST section | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_add_lesson_to_others` | POST lesson | 403 Forbidden | **NEEDS TEST** |

### 2.2 Assessment Isolation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cm_cannot_update_others_assessment` | PATCH other's | 403 Forbidden | EXISTS |
| `test_cm_cannot_delete_others_assessment` | DELETE other's | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_grade_others_attempts` | POST grade | 403 Forbidden | EXISTS |
| `test_cm_cannot_add_questions_to_others` | POST questions | 403 Forbidden | **NEEDS TEST** |

### 2.3 Enrollment Isolation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_cannot_view_others_progress` | GET progress | 403 Forbidden | **NEEDS TEST** |
| `test_learner_cannot_modify_others_enrollment` | PATCH enrollment | 403 Forbidden | **NEEDS TEST** |
| `test_learner_cannot_drop_others_enrollment` | DELETE other's | 404 Not Found | EXISTS |

### 2.4 Attempt Isolation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_cannot_view_others_attempt` | GET attempt | 403 Forbidden | EXISTS |
| `test_learner_cannot_submit_others_attempt` | POST submit | 403 Forbidden | EXISTS |
| `test_learner_cannot_view_others_answers` | GET answers | 403 Forbidden | **NEEDS TEST** |

### 2.5 Rating Isolation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_learner_cannot_update_others_rating` | PATCH rating | 403 Forbidden | EXISTS |
| `test_learner_cannot_delete_others_rating` | DELETE rating | 403 Forbidden | EXISTS |
| `test_cannot_rate_as_another_user` | Rate impersonation | 403 Forbidden | **NEEDS TEST** |

---

## 3. Cross-Resource Validation

### 3.1 Course-Assessment Mismatch

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_start_assessment_from_wrong_course` | Wrong course URL | 404 Not Found | EXISTS |
| `test_cannot_view_attempt_through_wrong_course` | Wrong course URL | 403 Forbidden | EXISTS |
| `test_cannot_submit_through_wrong_course` | Wrong course URL | 403 Forbidden | EXISTS |
| `test_assessment_course_id_mismatch_rejected` | Mismatched IDs | 404 Not Found | **NEEDS TEST** |

### 3.2 Section-Lesson Mismatch

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_access_lesson_from_wrong_section` | Wrong section | 404 Not Found | **NEEDS TEST** |
| `test_cannot_create_lesson_in_wrong_section` | Wrong section | 404 Not Found | **NEEDS TEST** |

### 3.3 Attempt-Answer Mismatch

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_answer_attempt_mismatch_rejected` | Wrong attempt | 422 Validation | **NEEDS TEST** |
| `test_cannot_grade_answer_from_wrong_attempt` | Wrong attempt | 403 Forbidden | **NEEDS TEST** |

---

## 4. Status-Based Restrictions

### 4.1 Draft Course Restrictions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_enroll_in_draft_course` | Enroll draft | 403 Forbidden | EXISTS |
| `test_learner_cannot_view_draft_course` | View draft | 403 Forbidden | **NEEDS TEST** |
| `test_draft_course_hidden_from_browse` | Browse | Not in list | **NEEDS TEST** |

### 4.2 Archived Course Restrictions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_enroll_in_archived_course` | Enroll archived | 403 Forbidden | EXISTS |
| `test_archived_course_hidden_from_browse` | Browse | Not in list | **NEEDS TEST** |
| `test_existing_enrollments_preserved_on_archive` | Archive with enrollments | Enrollments kept | **NEEDS TEST** |

### 4.3 Draft Assessment Restrictions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_start_draft_assessment` | Start draft | 403 Forbidden | EXISTS |
| `test_learner_cannot_view_draft_assessment` | View draft | 403 Forbidden | **NEEDS TEST** |
| `test_draft_assessment_hidden_from_enrolled` | View course | Not in list | **NEEDS TEST** |

### 4.4 Archived Assessment Restrictions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_start_archived_assessment` | Start archived | 403 Forbidden | **NEEDS TEST** |
| `test_archived_assessment_hidden` | View course | Not in list | **NEEDS TEST** |
| `test_existing_attempts_preserved_on_archive` | Archive with attempts | Attempts kept | **NEEDS TEST** |

### 4.5 Enrollment Status Restrictions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_dropped_cannot_view_lessons` | Dropped views | 403 Forbidden | EXISTS |
| `test_dropped_cannot_update_progress` | Dropped updates | 403 Forbidden | EXISTS |
| `test_dropped_cannot_start_assessment` | Dropped starts | 403 Forbidden | **NEEDS TEST** |
| `test_completed_can_still_view_lessons` | Completed views | 200 OK | **NEEDS TEST** |
| `test_completed_can_retake_assessments` | Completed retakes | Allowed | **NEEDS TEST** |

### 4.6 Attempt Status Restrictions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_submit_already_submitted` | Submit submitted | 403 Forbidden | EXISTS |
| `test_cannot_submit_graded_attempt` | Submit graded | 403 Forbidden | EXISTS |
| `test_cannot_grade_in_progress_attempt` | Grade in_progress | 403 Forbidden | **NEEDS TEST** |
| `test_cannot_modify_graded_attempt` | Modify graded | 403 Forbidden | **NEEDS TEST** |

---

## 5. Invitation Security

### 5.1 Invitation Access Control

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_accept_others_invitation` | Accept other's | 403 Forbidden | EXISTS |
| `test_cannot_decline_others_invitation` | Decline other's | 403 Forbidden | **NEEDS TEST** |
| `test_cannot_view_others_invitations` | View other's | 403 Forbidden | **NEEDS TEST** |
| `test_expired_invitation_cannot_be_accepted` | Accept expired | 422 Validation | **NEEDS TEST** |

### 5.2 Invitation Validation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cannot_invite_non_learner` | Invite CM | 422 Validation | EXISTS |
| `test_cannot_invite_already_enrolled` | Invite enrolled | 422 Validation | EXISTS |
| `test_cannot_duplicate_pending_invitation` | Duplicate | 422 Validation | EXISTS |

---

## 6. Published Content Protection

### 6.1 Content Manager Cannot Modify Published

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_cm_cannot_edit_published_course_title` | PATCH title | 403 Forbidden | EXISTS |
| `test_cm_cannot_add_section_to_published` | POST section | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_add_lesson_to_published` | POST lesson | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_delete_section_from_published` | DELETE section | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_delete_lesson_from_published` | DELETE lesson | 403 Forbidden | **NEEDS TEST** |
| `test_cm_cannot_reorder_in_published` | POST reorder | 403 Forbidden | **NEEDS TEST** |

### 6.2 Admin CAN Modify Published

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_edit_published_course` | Admin PATCH | Updated | EXISTS |
| `test_admin_can_add_section_to_published` | Admin POST | Created | **NEEDS TEST** |
| `test_admin_can_add_lesson_to_published` | Admin POST | Created | **NEEDS TEST** |

---

## 7. Rate Limiting

### 7.1 Authentication Rate Limits

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_login_rate_limited` | 5+ failed logins | 429 Too Many | EXISTS in `AuthenticationTest` |
| `test_password_update_rate_limited` | 6+ attempts/min | 429 Too Many | **NEEDS TEST** |
| `test_password_reset_rate_limited` | Multiple requests | 429 Too Many | **NEEDS TEST** |

### 7.2 API Rate Limits

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_api_rate_limited` | Many requests | 429 Too Many | **NEEDS TEST** |

---

## 8. Input Validation Security

### 8.1 XSS Prevention

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_course_title_escapes_html` | `<script>` in title | Escaped | **NEEDS TEST** |
| `test_lesson_content_escapes_unsafe_html` | Unsafe HTML | Sanitized | **NEEDS TEST** |
| `test_rating_review_escapes_html` | `<script>` in review | Escaped | **NEEDS TEST** |

### 8.2 SQL Injection Prevention

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_search_prevents_sql_injection` | SQL in search | Safe query | **NEEDS TEST** |
| `test_filter_prevents_sql_injection` | SQL in filter | Safe query | **NEEDS TEST** |

---

## 9. Implementation Examples

### 9.1 Authorization Boundary Test

```php
// tests/Feature/Authorization/RoleEscalationPreventionTest.php
<?php

use App\Models\{User, Course};

describe('Role Escalation Prevention', function () {

    describe('Learner Cannot Access Management', function () {

        it('learner cannot access course creation', function () {
            $learner = User::factory()->create(['role' => 'learner']);

            $this->actingAs($learner)
                ->get(route('courses.create'))
                ->assertForbidden();
        });

        it('learner cannot publish course even with direct route', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->draft()->create();

            $this->actingAs($learner)
                ->post(route('courses.publish', $course))
                ->assertForbidden();

            expect($course->refresh()->status)->toBe('draft');
        });

        it('learner cannot access instructor dashboard', function () {
            $learner = User::factory()->create(['role' => 'learner']);

            $this->actingAs($learner)
                ->get(route('dashboard'))
                ->assertRedirect(route('learner.dashboard'));
        });

    });

    describe('Content Manager Publishing Restrictions', function () {

        it('content manager cannot publish own course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->post(route('courses.publish', $course))
                ->assertForbidden();

            expect($course->refresh()->status)->toBe('draft');
        });

        it('content manager cannot set status directly', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->patch(route('courses.status.update', $course), [
                    'status' => 'published',
                ])
                ->assertForbidden();
        });

    });

});
```

### 9.2 Resource Isolation Test

```php
// tests/Feature/Authorization/ResourceIsolationTest.php
<?php

use App\Models\{User, Course, Assessment, Enrollment};

describe('Resource Isolation', function () {

    it('content manager cannot update others course', function () {
        $cm1 = User::factory()->create(['role' => 'content_manager']);
        $cm2 = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);

        $this->actingAs($cm1)
            ->patch(route('courses.update', $course), [
                'title' => 'Hijacked Title',
            ])
            ->assertForbidden();

        expect($course->refresh()->title)->not->toBe('Hijacked Title');
    });

    it('learner cannot view others enrollment progress', function () {
        $course = createPublishedCourseWithContent();
        $learner1 = User::factory()->create(['role' => 'learner']);
        $learner2 = User::factory()->create(['role' => 'learner']);

        Enrollment::factory()->create([
            'user_id' => $learner1->id,
            'course_id' => $course->id,
            'progress_percentage' => 50,
        ]);

        $enrollment2 = Enrollment::factory()->create([
            'user_id' => $learner2->id,
            'course_id' => $course->id,
        ]);

        // Learner 2 shouldn't see learner 1's progress
        $this->actingAs($learner2)
            ->get(route('learner.dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) =>
                $page->where('myLearning.0.progress_percentage', 0)
            );
    });

});
```

---

## 10. Test Priority

### Priority 1 (Critical - Security)
1. Role escalation prevention tests
2. Resource isolation tests
3. Cross-resource validation
4. Published content protection

### Priority 2 (High - Access Control)
5. Status-based restrictions
6. Invitation security
7. Trainer-specific restrictions

### Priority 3 (Medium - Defense in Depth)
8. Rate limiting tests
9. Input validation security
10. API authorization parity

---

## 11. Key Files Reference

- `app/Policies/CoursePolicy.php` - Course authorization rules
- `app/Policies/AssessmentPolicy.php` - Assessment authorization
- `app/Policies/AssessmentAttemptPolicy.php` - Attempt authorization
- `app/Http/Middleware/` - Request middleware
- `routes/web.php` - Route middleware assignments
- `tests/Feature/Auth/AuthenticationTest.php` - Existing auth tests
