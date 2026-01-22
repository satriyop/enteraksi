# 02 - Instructor Journey Integration Test Plan

## Overview

This document covers the Content Manager and Trainer workflows in the Enteraksi LMS, including course creation, content management, assessment creation, and student management.

---

## Role Comparison

| Capability | Content Manager | Trainer | LMS Admin |
|------------|-----------------|---------|-----------|
| Create courses | Own only | Own only | Any |
| Edit draft courses | Own only | Own only | Any |
| Edit published courses | NO | NO | YES |
| Publish/unpublish | NO | NO | YES |
| Archive courses | NO | NO | YES |
| Delete draft courses | Own only | Own only | Any |
| Create assessments | Own course | Own course | Any |
| Publish assessments | NO | NO | YES |
| Grade attempts | Own assessment | **UNCLEAR** | Any |
| Invite learners | Own course | ANY course | Any |

---

## 1. Course Creation and Management

### 1.1 Course Creation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_create_course` | POST courses | Course created in draft | EXISTS in `CourseTest` |
| `test_course_created_in_draft_status` | After create | status='draft' | EXISTS |
| `test_course_owner_set_to_creator` | After create | user_id = creator | EXISTS |
| `test_trainer_can_create_course` | Trainer POST | Course created | **NEEDS TEST** |
| `test_learner_cannot_create_course` | Learner POST | 403 Forbidden | EXISTS |
| `test_course_with_optional_fields` | Category, tags, duration | All saved | **NEEDS TEST** |
| `test_course_slug_auto_generated` | No slug provided | Slug created | **NEEDS TEST** |

### 1.2 Course Update Permissions

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_update_own_draft_course` | PATCH own draft | Updated | EXISTS |
| `test_content_manager_cannot_update_published_course` | PATCH published | 403 Forbidden | EXISTS in `CoursePublishingStateMachineTest` |
| `test_content_manager_cannot_update_others_draft` | PATCH other's | 403 Forbidden | EXISTS |
| `test_trainer_can_update_own_draft_course` | Trainer PATCH | Updated | **NEEDS TEST** |
| `test_trainer_cannot_update_others_draft` | Trainer other's | 403 Forbidden | **NEEDS TEST** |
| `test_lms_admin_can_update_any_published` | Admin PATCH | Updated | EXISTS |

### 1.3 Course Deletion

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_delete_own_draft` | DELETE own draft | Soft deleted | EXISTS |
| `test_content_manager_cannot_delete_published` | DELETE published | 403 Forbidden | EXISTS |
| `test_content_manager_cannot_delete_others` | DELETE other's | 403 Forbidden | **NEEDS TEST** |
| `test_soft_delete_preserves_enrollments` | DELETE course | Enrollments remain | EXISTS in `EdgeCasesAndBusinessRulesTest` |

---

## 2. State Machine Constraints (Authorization Boundaries)

### 2.1 Publishing Workflow (Content Manager CANNOT)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_cannot_publish_course` | POST publish | 403 Forbidden | EXISTS in `CoursePublishingStateMachineTest` |
| `test_content_manager_cannot_unpublish_course` | POST unpublish | 403 Forbidden | EXISTS |
| `test_content_manager_cannot_archive_course` | POST archive | 403 Forbidden | EXISTS |
| `test_content_manager_cannot_set_status` | PATCH status | 403 Forbidden | EXISTS |
| `test_content_manager_cannot_set_visibility` | PATCH visibility | 403 Forbidden | EXISTS |

### 2.2 Publishing Workflow (Trainer CANNOT)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_trainer_cannot_publish_course` | Trainer POST publish | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_unpublish_course` | Trainer POST unpublish | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_archive_course` | Trainer POST archive | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_set_status` | Trainer PATCH status | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_cannot_set_visibility` | Trainer PATCH visibility | 403 Forbidden | **NEEDS TEST** |

### 2.3 Editability Based on State

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_draft_course_is_editable_by_owner` | Check draft | canBeEdited=true | EXISTS |
| `test_published_course_not_editable_by_cm` | Check published | canBeEdited=false for CM | EXISTS |
| `test_archived_course_is_editable_by_owner` | Check archived | canBeEdited=true | EXISTS |

---

## 3. Section Management (HIGH PRIORITY - NEEDS TESTS)

### 3.1 Section CRUD

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_create_section_in_own_draft` | POST section | Section created | **NEEDS TEST** |
| `test_content_manager_can_update_section_in_own_draft` | PATCH section | Updated | **NEEDS TEST** |
| `test_content_manager_can_delete_section_in_own_draft` | DELETE section | Deleted | **NEEDS TEST** |
| `test_content_manager_can_reorder_sections` | POST reorder | Order updated | **NEEDS TEST** |
| `test_content_manager_cannot_modify_sections_in_published` | Any operation | 403 Forbidden | **NEEDS TEST** |
| `test_content_manager_cannot_modify_others_sections` | Other's course | 403 Forbidden | **NEEDS TEST** |
| `test_section_order_auto_increments` | Create multiple | Sequential order | **NEEDS TEST** |
| `test_section_deletion_cascades_to_lessons` | DELETE section | Lessons deleted | **NEEDS TEST** |

---

## 4. Lesson Management (HIGH PRIORITY - NEEDS TESTS)

### 4.1 Lesson CRUD

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_create_lesson` | POST lesson | Lesson created | **NEEDS TEST** |
| `test_can_create_video_lesson` | content_type=video | Video lesson | **NEEDS TEST** |
| `test_can_create_text_lesson` | content_type=text | Text lesson | **NEEDS TEST** |
| `test_can_create_document_lesson` | content_type=document | Document lesson | **NEEDS TEST** |
| `test_can_create_audio_lesson` | content_type=audio | Audio lesson | **NEEDS TEST** |
| `test_can_create_youtube_lesson` | content_type=youtube | YouTube lesson | **NEEDS TEST** |
| `test_can_create_conference_lesson` | content_type=conference | Conference lesson | **NEEDS TEST** |
| `test_content_manager_can_update_lesson` | PATCH lesson | Updated | **NEEDS TEST** |
| `test_content_manager_can_delete_lesson` | DELETE lesson | Deleted | **NEEDS TEST** |
| `test_content_manager_can_reorder_lessons` | POST reorder | Order updated | **NEEDS TEST** |
| `test_cannot_modify_lessons_in_published_course` | Any operation | 403 Forbidden | **NEEDS TEST** |
| `test_cannot_modify_others_lessons` | Other's course | 403 Forbidden | **NEEDS TEST** |
| `test_lesson_deletion_preserves_progress_records` | DELETE lesson | Progress remains | **NEEDS TEST** |

### 4.2 Lesson Preview

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_preview_own_lesson` | GET preview | 200 OK | **NEEDS TEST** |
| `test_content_manager_cannot_preview_others_lesson` | GET other's | 403 Forbidden | **NEEDS TEST** |
| `test_learner_cannot_access_preview` | Learner GET | 403 Forbidden | **NEEDS TEST** |

---

## 5. Media Upload and Management

### 5.1 Media Upload Authorization

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_upload_video` | POST video | Media created | EXISTS in `MediaUploadTest` |
| `test_content_manager_can_upload_audio` | POST audio | Media created | EXISTS |
| `test_content_manager_can_upload_document` | POST document | Media created | EXISTS |
| `test_content_manager_cannot_upload_to_others` | POST other's | 403 Forbidden | EXISTS |
| `test_learner_cannot_upload_media` | Learner POST | 403 Forbidden | EXISTS |
| `test_guest_cannot_upload_media` | Guest POST | 401 Unauthorized | EXISTS |
| `test_trainer_can_upload_to_own_course` | Trainer POST | Media created | **NEEDS TEST** |

### 5.2 Media Deletion

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_delete_own_media` | DELETE own | Deleted | EXISTS |
| `test_content_manager_cannot_delete_others_media` | DELETE other's | 403 Forbidden | EXISTS |
| `test_lms_admin_can_delete_any_media` | Admin DELETE | Deleted | EXISTS |

### 5.3 Media Validation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_invalid_collection_name_rejected` | Bad collection | 422 Validation | EXISTS |
| `test_upload_to_nonexistent_lesson_404` | No lesson | 404 Not Found | EXISTS |
| `test_file_stored_in_correct_path` | After upload | Correct path | EXISTS |
| `test_media_attributes_set_correctly` | After upload | All attributes | EXISTS |

---

## 6. Assessment Management

### 6.1 Assessment CRUD

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_create_assessment` | POST assessment | Created | EXISTS in `AssessmentCrudTest` |
| `test_content_manager_can_update_own_draft` | PATCH draft | Updated | EXISTS |
| `test_content_manager_can_update_published` | PATCH published | Updated | EXISTS (policy allows) |
| `test_content_manager_cannot_update_others` | PATCH other's | 403 Forbidden | EXISTS |
| `test_content_manager_cannot_delete_published` | DELETE published | 403 Forbidden | EXISTS |
| `test_content_manager_cannot_publish` | POST publish | 403 Forbidden | EXISTS in `AssessmentPolicyTest` |
| `test_trainer_can_create_assessment` | Trainer POST | Created | **NEEDS TEST** |
| `test_trainer_cannot_publish_assessment` | Trainer publish | 403 Forbidden | **NEEDS TEST** |

### 6.2 Assessment Filtering

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_assessments_filter_by_status` | Filter status | Filtered results | EXISTS |
| `test_assessments_filter_by_search` | Search query | Filtered results | EXISTS |

---

## 7. Question Management

### 7.1 Question CRUD

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_can_create_multiple_choice_question` | POST MC | Created | EXISTS in `QuestionCrudTest` |
| `test_can_create_true_false_question` | POST TF | Created | EXISTS |
| `test_can_create_short_answer_question` | POST SA | Created | EXISTS |
| `test_can_create_essay_question` | POST essay | Created | EXISTS |
| `test_can_create_file_upload_question` | POST file | Created | EXISTS |
| `test_can_create_matching_question` | POST matching | Created | EXISTS |
| `test_can_update_questions` | PATCH question | Updated | EXISTS |
| `test_can_delete_questions` | DELETE question | Deleted | EXISTS |
| `test_multiple_correct_options` | MC with 2+ correct | Saved | EXISTS |

### 7.2 Question Validation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_question_requires_text` | Empty text | 422 Validation | EXISTS |
| `test_question_requires_valid_type` | Invalid type | 422 Validation | EXISTS |
| `test_multiple_choice_requires_options` | No options | 422 Validation | EXISTS |
| `test_question_points_must_be_positive` | points=-1 | 422 Validation | EXISTS |

---

## 8. Student Invitations

### 8.1 Single Invitation

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_invite_to_own_course` | POST invitation | Created | EXISTS in `CourseInvitationAdminTest` |
| `test_content_manager_cannot_invite_to_others_course` | POST other's | 403 Forbidden | EXISTS |
| `test_trainer_can_invite_to_any_course` | Trainer POST | Created | EXISTS |
| `test_lms_admin_can_invite_to_any` | Admin POST | Created | EXISTS |
| `test_cannot_invite_non_learner` | Invite CM | 422 Validation | EXISTS |
| `test_cannot_invite_already_enrolled` | Invite enrolled | 422 Validation | EXISTS |
| `test_cannot_send_duplicate_pending` | Duplicate | 422 Validation | EXISTS |
| `test_invitation_with_expiration` | With expires_at | Saved | EXISTS |

### 8.2 Invitation Management

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_admin_can_cancel_pending` | Admin DELETE | Cancelled | EXISTS |
| `test_inviter_can_cancel_own` | Inviter DELETE | Cancelled | EXISTS |
| `test_cannot_cancel_accepted` | DELETE accepted | 422 Validation | EXISTS |
| `test_course_show_includes_invitations` | GET course | Has invitations | EXISTS |

### 8.3 Bulk Invitation (CSV Import)

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_bulk_import_from_csv` | POST bulk | Multiple created | EXISTS |
| `test_bulk_import_skips_invalid_emails` | Bad emails | Skipped | EXISTS |
| `test_content_manager_can_bulk_import_own` | CM bulk | Created | **NEEDS TEST** |
| `test_content_manager_cannot_bulk_import_others` | CM other's | 403 Forbidden | **NEEDS TEST** |
| `test_trainer_can_bulk_import_any` | Trainer bulk | Created | **NEEDS TEST** |

### 8.4 Search Learners API

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_search_returns_matching_learners` | Search query | Learners returned | EXISTS |
| `test_search_excludes_enrolled_and_invited` | Search | Filtered | EXISTS |

---

## 9. Grading Workflows

### 9.1 Grading Authorization

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_grade_own_assessment` | Grade own | 200 OK | EXISTS in `AssessmentPolicyTest` |
| `test_content_manager_cannot_grade_others` | Grade other's | 403 Forbidden | EXISTS |
| `test_lms_admin_can_grade_any` | Admin grade | 200 OK | EXISTS |
| `test_learner_cannot_grade` | Learner grade | 403 Forbidden | EXISTS |
| `test_trainer_grading_authorization` | Trainer grade | **UNCLEAR** | **NEEDS TEST** |

### 9.2 Grading Workflow

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_essay_requires_manual_grading` | Check essay | requiresManualGrading=true | EXISTS in `AssessmentManualGradingTest` |
| `test_file_upload_requires_manual` | Check file | requiresManualGrading=true | EXISTS |
| `test_calculate_score_updates_attempt` | After grade | Totals updated | EXISTS |
| `test_mixed_auto_manual_scoring` | Both types | Correct combined | EXISTS |
| `test_graded_by_tracks_grader` | After grade | graded_by set | EXISTS |
| `test_graded_at_timestamp_set` | After grade | graded_at set | EXISTS |

### 9.3 Viewing Attempts

| Test Case | Description | Expected | Status |
|-----------|-------------|----------|--------|
| `test_content_manager_can_view_own_assessment_attempts` | View own | 200 OK | EXISTS |
| `test_content_manager_cannot_view_others_attempts` | View other's | 403 Forbidden | EXISTS |
| `test_course_show_includes_enrollments_for_admin` | Admin view | Has enrollments | **NEEDS TEST** |
| `test_trainer_can_view_enrollments` | Trainer view | Has enrollments | **NEEDS TEST** |

---

## 10. End-to-End Instructor Journeys (CRITICAL - NEW)

### 10.1 Complete Instructor Workflows

| Test Case | Description | Status |
|-----------|-------------|--------|
| `test_full_course_creation_workflow` | Create course, sections, lessons, assessments | **NEEDS TEST** |
| `test_cm_creates_admin_publishes` | CM creates, admin publishes, learner enrolls | **NEEDS TEST** |
| `test_assessment_lifecycle` | Create, add questions, publish, learners attempt | **NEEDS TEST** |
| `test_invitation_to_enrollment_workflow` | Invite, accept, enroll, complete | **NEEDS TEST** |
| `test_grading_workflow_end_to_end` | Submit, grade, view results | **NEEDS TEST** |

---

## 11. Implementation Notes

### Suggested Test File: Section/Lesson CRUD

```php
// tests/Feature/ContentManagement/SectionCrudTest.php
<?php

use App\Models\{User, Course, CourseSection};

describe('Section CRUD', function () {

    it('content manager can create section in own draft course', function () {
        $cm = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

        $this->actingAs($cm)
            ->post(route('courses.sections.store', $course), [
                'title' => 'New Section',
                'description' => 'Section description',
            ])
            ->assertRedirect();

        expect($course->sections()->count())->toBe(1);
    });

    it('content manager cannot create section in published course', function () {
        $cm = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->published()->create(['user_id' => $cm->id]);

        $this->actingAs($cm)
            ->post(route('courses.sections.store', $course), [
                'title' => 'New Section',
            ])
            ->assertForbidden();
    });

    it('content manager cannot create section in others course', function () {
        $cm = User::factory()->create(['role' => 'content_manager']);
        $otherCm = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->draft()->create(['user_id' => $otherCm->id]);

        $this->actingAs($cm)
            ->post(route('courses.sections.store', $course), [
                'title' => 'New Section',
            ])
            ->assertForbidden();
    });

});
```

### Key Policy Gap Identified

**Trainer Grading**: The `AssessmentPolicy::grade()` method only checks `isContentManager()`:

```php
// Current implementation (potential bug)
public function grade(User $user, AssessmentAttempt $attempt): bool
{
    return $user->isLmsAdmin()
        || ($user->isContentManager() && $attempt->assessment->user_id === $user->id);
}
```

Trainers cannot grade! This may be intentional or a bug. Test to verify and document.

---

## 12. Priority Summary

### HIGH Priority (Security/Authorization)
1. Section/Lesson CRUD authorization tests
2. Trainer role comprehensive tests (publish, archive, grade)
3. Bulk invitation authorization

### MEDIUM Priority (Workflow Completeness)
4. Content modification in published courses
5. End-to-end instructor workflows
6. Trainer grading capability verification

### LOW Priority (Edge Cases)
7. Media upload for trainers
8. Lesson preview authorization
9. Cross-course authorization edge cases

---

## 13. Key Files Reference

- `app/Policies/CoursePolicy.php` - Course authorization
- `app/Policies/AssessmentPolicy.php` - Assessment authorization (check grade method)
- `app/Policies/CourseInvitationPolicy.php` - Invitation authorization
- `tests/Feature/CoursePublishingStateMachineTest.php` - State machine pattern
- `tests/Feature/CourseInvitationAdminTest.php` - Invitation test pattern
- `tests/Feature/MediaUploadTest.php` - Media test pattern
