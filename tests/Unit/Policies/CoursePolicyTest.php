<?php

namespace Tests\Unit\Policies;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\CoursePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for CoursePolicy.
 *
 * These tests verify authorization logic in isolation for course operations.
 * Critical for ensuring authorization rules are preserved through refactoring.
 *
 * Test Matrix:
 * - User Roles: lms_admin, content_manager, learner, trainer
 * - Course Status: draft, published, archived
 * - Course Visibility: public, restricted, hidden
 * - Ownership: owner vs non-owner
 */
class CoursePolicyTest extends TestCase
{
    use RefreshDatabase;

    private CoursePolicy $policy;

    private User $lmsAdmin;

    private User $contentManager;

    private User $otherContentManager;

    private User $learner;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CoursePolicy;

        $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);
        $this->otherContentManager = User::factory()->create(['role' => 'content_manager']);
        $this->learner = User::factory()->create(['role' => 'learner']);
        $this->trainer = User::factory()->create(['role' => 'trainer']);
    }

    // ========== viewAny ==========

    public function test_any_user_can_view_any_courses(): void
    {
        $this->assertTrue($this->policy->viewAny($this->lmsAdmin));
        $this->assertTrue($this->policy->viewAny($this->contentManager));
        $this->assertTrue($this->policy->viewAny($this->learner));
        $this->assertTrue($this->policy->viewAny($this->trainer));
    }

    // ========== view ==========

    public function test_lms_admin_can_view_any_course(): void
    {
        $draftCourse = Course::factory()->create(['status' => 'draft']);
        $publishedCourse = Course::factory()->published()->create();
        $archivedCourse = Course::factory()->create(['status' => 'archived']);

        $this->assertTrue($this->policy->view($this->lmsAdmin, $draftCourse));
        $this->assertTrue($this->policy->view($this->lmsAdmin, $publishedCourse));
        $this->assertTrue($this->policy->view($this->lmsAdmin, $archivedCourse));
    }

    public function test_content_manager_can_view_own_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        $this->assertTrue($this->policy->view($this->contentManager, $course));
    }

    public function test_content_manager_can_view_other_published_public_course(): void
    {
        $course = Course::factory()->published()->create([
            'user_id' => $this->otherContentManager->id,
            'visibility' => 'public',
        ]);

        $this->assertTrue($this->policy->view($this->contentManager, $course));
    }

    public function test_enrolled_learner_can_view_draft_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        $this->assertTrue($this->policy->view($this->learner, $course));
    }

    public function test_learner_can_view_published_public_course(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'public',
        ]);

        $this->assertTrue($this->policy->view($this->learner, $course));
    }

    public function test_learner_cannot_view_draft_course_without_enrollment(): void
    {
        $course = Course::factory()->create([
            'status' => 'draft',
            'visibility' => 'public',
        ]);

        $this->assertFalse($this->policy->view($this->learner, $course));
    }

    public function test_invited_learner_can_view_restricted_course(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'restricted',
        ]);

        CourseInvitation::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($this->policy->view($this->learner, $course));
    }

    public function test_uninvited_learner_cannot_view_restricted_course(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'restricted',
        ]);

        $this->assertFalse($this->policy->view($this->learner, $course));
    }

    // ========== create ==========

    public function test_lms_admin_can_create_course(): void
    {
        $this->assertTrue($this->policy->create($this->lmsAdmin));
    }

    public function test_content_manager_can_create_course(): void
    {
        $this->assertTrue($this->policy->create($this->contentManager));
    }

    public function test_learner_cannot_create_course(): void
    {
        $this->assertFalse($this->policy->create($this->learner));
    }

    // ========== update ==========

    public function test_lms_admin_can_update_any_course(): void
    {
        $draftCourse = Course::factory()->create(['status' => 'draft']);
        $publishedCourse = Course::factory()->published()->create();

        $this->assertTrue($this->policy->update($this->lmsAdmin, $draftCourse));
        $this->assertTrue($this->policy->update($this->lmsAdmin, $publishedCourse));
    }

    public function test_content_manager_can_update_own_draft_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        $this->assertTrue($this->policy->update($this->contentManager, $course));
    }

    public function test_content_manager_cannot_update_own_published_course(): void
    {
        $course = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
        ]);

        $this->assertFalse($this->policy->update($this->contentManager, $course));
    }

    public function test_content_manager_cannot_update_other_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->otherContentManager->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->update($this->contentManager, $course));
    }

    public function test_learner_cannot_update_course(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->assertFalse($this->policy->update($this->learner, $course));
    }

    // ========== delete ==========

    public function test_lms_admin_can_delete_any_course(): void
    {
        $draftCourse = Course::factory()->create(['status' => 'draft']);
        $publishedCourse = Course::factory()->published()->create();

        $this->assertTrue($this->policy->delete($this->lmsAdmin, $draftCourse));
        $this->assertTrue($this->policy->delete($this->lmsAdmin, $publishedCourse));
    }

    public function test_content_manager_can_delete_own_draft_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        $this->assertTrue($this->policy->delete($this->contentManager, $course));
    }

    public function test_content_manager_cannot_delete_own_published_course(): void
    {
        $course = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
        ]);

        $this->assertFalse($this->policy->delete($this->contentManager, $course));
    }

    public function test_content_manager_cannot_delete_other_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->otherContentManager->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->delete($this->contentManager, $course));
    }

    public function test_learner_cannot_delete_course(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->assertFalse($this->policy->delete($this->learner, $course));
    }

    // ========== restore ==========

    public function test_lms_admin_can_restore_course(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);
        $course->delete();

        $this->assertTrue($this->policy->restore($this->lmsAdmin, $course));
    }

    public function test_content_manager_cannot_restore_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);
        $course->delete();

        $this->assertFalse($this->policy->restore($this->contentManager, $course));
    }

    // ========== forceDelete ==========

    public function test_lms_admin_can_force_delete_course(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->assertTrue($this->policy->forceDelete($this->lmsAdmin, $course));
    }

    public function test_content_manager_cannot_force_delete_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->forceDelete($this->contentManager, $course));
    }

    // ========== publish ==========

    public function test_lms_admin_can_publish_course(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->assertTrue($this->policy->publish($this->lmsAdmin, $course));
    }

    public function test_content_manager_cannot_publish_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->publish($this->contentManager, $course));
    }

    public function test_learner_cannot_publish_course(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->assertFalse($this->policy->publish($this->learner, $course));
    }

    // ========== unpublish ==========

    public function test_lms_admin_can_unpublish_course(): void
    {
        $course = Course::factory()->published()->create();

        $this->assertTrue($this->policy->unpublish($this->lmsAdmin, $course));
    }

    public function test_content_manager_cannot_unpublish_course(): void
    {
        $course = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
        ]);

        $this->assertFalse($this->policy->unpublish($this->contentManager, $course));
    }

    // ========== archive ==========

    public function test_lms_admin_can_archive_course(): void
    {
        $course = Course::factory()->published()->create();

        $this->assertTrue($this->policy->archive($this->lmsAdmin, $course));
    }

    public function test_content_manager_cannot_archive_course(): void
    {
        $course = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
        ]);

        $this->assertFalse($this->policy->archive($this->contentManager, $course));
    }

    // ========== setStatus ==========

    public function test_lms_admin_can_set_status(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->assertTrue($this->policy->setStatus($this->lmsAdmin, $course));
    }

    public function test_content_manager_cannot_set_status(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->setStatus($this->contentManager, $course));
    }

    // ========== setVisibility ==========

    public function test_lms_admin_can_set_visibility(): void
    {
        $course = Course::factory()->create(['visibility' => 'public']);

        $this->assertTrue($this->policy->setVisibility($this->lmsAdmin, $course));
    }

    public function test_content_manager_cannot_set_visibility(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'public',
        ]);

        $this->assertFalse($this->policy->setVisibility($this->contentManager, $course));
    }

    // ========== enroll ==========

    public function test_learner_can_enroll_in_published_public_course(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'public',
        ]);

        $this->assertTrue($this->policy->enroll($this->learner, $course));
    }

    public function test_learner_cannot_enroll_in_draft_course(): void
    {
        $course = Course::factory()->create([
            'status' => 'draft',
            'visibility' => 'public',
        ]);

        $this->assertFalse($this->policy->enroll($this->learner, $course));
    }

    public function test_learner_cannot_enroll_in_archived_course(): void
    {
        $course = Course::factory()->create([
            'status' => 'archived',
            'visibility' => 'public',
        ]);

        $this->assertFalse($this->policy->enroll($this->learner, $course));
    }

    public function test_already_enrolled_learner_cannot_double_enroll(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'public',
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        $this->assertFalse($this->policy->enroll($this->learner, $course));
    }

    public function test_invited_learner_can_enroll_in_restricted_course(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'restricted',
        ]);

        CourseInvitation::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($this->policy->enroll($this->learner, $course));
    }

    public function test_uninvited_learner_cannot_enroll_in_restricted_course(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'restricted',
        ]);

        $this->assertFalse($this->policy->enroll($this->learner, $course));
    }

    public function test_learner_cannot_enroll_in_hidden_course(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'hidden',
        ]);

        $this->assertFalse($this->policy->enroll($this->learner, $course));
    }

    // ========== Edge Cases ==========

    public function test_completed_enrollment_allows_re_enrollment(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'public',
        ]);

        // Create completed enrollment
        Enrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        // Can re-enroll since previous enrollment is completed
        $this->assertTrue($this->policy->enroll($this->learner, $course));
    }

    public function test_dropped_enrollment_allows_re_enrollment(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'public',
        ]);

        // Create dropped enrollment
        Enrollment::factory()->dropped()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        // Can re-enroll since previous enrollment is dropped
        $this->assertTrue($this->policy->enroll($this->learner, $course));
    }

    public function test_accepted_invitation_does_not_allow_enrollment(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'restricted',
        ]);

        // Create accepted invitation (not pending)
        CourseInvitation::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'status' => 'accepted',
        ]);

        // Cannot enroll because invitation is not pending
        $this->assertFalse($this->policy->enroll($this->learner, $course));
    }
}
