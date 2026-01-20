<?php

namespace Tests\Unit\Policies;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\AssessmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for AssessmentPolicy.
 *
 * These tests verify authorization logic in isolation, ensuring
 * that policy rules are preserved through refactoring.
 *
 * Test Matrix:
 * - User Roles: lms_admin, content_manager, learner, trainer
 * - Assessment States: draft, published, archived
 * - Ownership: owner vs non-owner
 * - Enrollment: enrolled vs not enrolled
 */
class AssessmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentPolicy $policy;

    private User $lmsAdmin;

    private User $contentManager;

    private User $otherContentManager;

    private User $learner;

    private User $trainer;

    private Course $course;

    private Assessment $draftAssessment;

    private Assessment $publishedAssessment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AssessmentPolicy;

        $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);
        $this->otherContentManager = User::factory()->create(['role' => 'content_manager']);
        $this->learner = User::factory()->create(['role' => 'learner']);
        $this->trainer = User::factory()->create(['role' => 'trainer']);

        $this->course = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
        ]);

        $this->draftAssessment = Assessment::factory()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
        ]);

        $this->publishedAssessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
        ]);
    }

    // ========== viewAny ==========

    public function test_lms_admin_can_view_any_assessments(): void
    {
        $this->assertTrue($this->policy->viewAny($this->lmsAdmin, $this->course));
    }

    public function test_content_manager_can_view_any_assessments_for_own_course(): void
    {
        $this->assertTrue($this->policy->viewAny($this->contentManager, $this->course));
    }

    public function test_content_manager_cannot_view_any_assessments_for_other_course(): void
    {
        $otherCourse = Course::factory()->create([
            'user_id' => $this->otherContentManager->id,
        ]);

        $this->assertFalse($this->policy->viewAny($this->contentManager, $otherCourse));
    }

    public function test_enrolled_learner_can_view_any_assessments(): void
    {
        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        $this->assertTrue($this->policy->viewAny($this->learner, $this->course));
    }

    public function test_unenrolled_learner_cannot_view_any_assessments(): void
    {
        $this->assertFalse($this->policy->viewAny($this->learner, $this->course));
    }

    // ========== view ==========

    public function test_lms_admin_can_view_any_assessment(): void
    {
        $this->assertTrue($this->policy->view($this->lmsAdmin, $this->draftAssessment, $this->course));
        $this->assertTrue($this->policy->view($this->lmsAdmin, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_can_view_own_assessment(): void
    {
        $this->assertTrue($this->policy->view($this->contentManager, $this->draftAssessment, $this->course));
    }

    public function test_content_manager_cannot_view_other_assessment(): void
    {
        $otherAssessment = Assessment::factory()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->otherContentManager->id,
        ]);

        $this->assertFalse($this->policy->view($this->contentManager, $otherAssessment, $this->course));
    }

    public function test_enrolled_learner_can_view_published_assessment(): void
    {
        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        $this->assertTrue($this->policy->view($this->learner, $this->publishedAssessment, $this->course));
    }

    public function test_enrolled_learner_cannot_view_draft_assessment(): void
    {
        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        $this->assertFalse($this->policy->view($this->learner, $this->draftAssessment, $this->course));
    }

    public function test_view_fails_when_assessment_not_in_course(): void
    {
        $otherCourse = Course::factory()->create();

        $this->assertFalse($this->policy->view($this->lmsAdmin, $this->draftAssessment, $otherCourse));
    }

    // ========== create ==========

    public function test_lms_admin_can_create_assessment(): void
    {
        $this->assertTrue($this->policy->create($this->lmsAdmin, $this->course));
    }

    public function test_content_manager_can_create_assessment_for_own_course(): void
    {
        $this->assertTrue($this->policy->create($this->contentManager, $this->course));
    }

    public function test_content_manager_cannot_create_assessment_for_other_course(): void
    {
        $otherCourse = Course::factory()->create([
            'user_id' => $this->otherContentManager->id,
        ]);

        $this->assertFalse($this->policy->create($this->contentManager, $otherCourse));
    }

    public function test_learner_cannot_create_assessment(): void
    {
        $this->assertFalse($this->policy->create($this->learner, $this->course));
    }

    // ========== update ==========

    public function test_lms_admin_can_update_any_assessment(): void
    {
        $this->assertTrue($this->policy->update($this->lmsAdmin, $this->draftAssessment, $this->course));
        $this->assertTrue($this->policy->update($this->lmsAdmin, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_can_update_own_draft_assessment(): void
    {
        $this->assertTrue($this->policy->update($this->contentManager, $this->draftAssessment, $this->course));
    }

    public function test_content_manager_can_update_own_published_assessment(): void
    {
        // Note: Current implementation allows CM to update published assessments they own
        $this->assertTrue($this->policy->update($this->contentManager, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_cannot_update_other_assessment(): void
    {
        $otherAssessment = Assessment::factory()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->otherContentManager->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->update($this->contentManager, $otherAssessment, $this->course));
    }

    public function test_learner_cannot_update_assessment(): void
    {
        $this->assertFalse($this->policy->update($this->learner, $this->draftAssessment, $this->course));
    }

    public function test_update_fails_when_assessment_not_in_course(): void
    {
        $otherCourse = Course::factory()->create();

        $this->assertFalse($this->policy->update($this->lmsAdmin, $this->draftAssessment, $otherCourse));
    }

    // ========== delete ==========

    public function test_lms_admin_can_delete_draft_assessment(): void
    {
        $this->assertTrue($this->policy->delete($this->lmsAdmin, $this->draftAssessment, $this->course));
    }

    public function test_lms_admin_cannot_delete_published_assessment(): void
    {
        // Note: Current implementation prevents deleting published assessments
        $this->assertFalse($this->policy->delete($this->lmsAdmin, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_can_delete_own_draft_assessment(): void
    {
        $this->assertTrue($this->policy->delete($this->contentManager, $this->draftAssessment, $this->course));
    }

    public function test_content_manager_cannot_delete_own_published_assessment(): void
    {
        $this->assertFalse($this->policy->delete($this->contentManager, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_cannot_delete_other_assessment(): void
    {
        $otherAssessment = Assessment::factory()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->otherContentManager->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->delete($this->contentManager, $otherAssessment, $this->course));
    }

    public function test_learner_cannot_delete_assessment(): void
    {
        $this->assertFalse($this->policy->delete($this->learner, $this->draftAssessment, $this->course));
    }

    // ========== publish ==========

    public function test_lms_admin_can_publish_assessment(): void
    {
        $this->assertTrue($this->policy->publish($this->lmsAdmin, $this->draftAssessment, $this->course));
    }

    public function test_content_manager_cannot_publish_assessment(): void
    {
        $this->assertFalse($this->policy->publish($this->contentManager, $this->draftAssessment, $this->course));
    }

    public function test_learner_cannot_publish_assessment(): void
    {
        $this->assertFalse($this->policy->publish($this->learner, $this->draftAssessment, $this->course));
    }

    public function test_publish_fails_when_assessment_not_in_course(): void
    {
        $otherCourse = Course::factory()->create();

        $this->assertFalse($this->policy->publish($this->lmsAdmin, $this->draftAssessment, $otherCourse));
    }

    // ========== attempt ==========

    public function test_attempt_delegates_to_assessment_model(): void
    {
        // Create a mock to verify delegation
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'max_attempts' => 3,
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // The result depends on Assessment::canBeAttemptedBy()
        $result = $this->policy->attempt($this->learner, $assessment, $this->course);
        $this->assertEquals($assessment->canBeAttemptedBy($this->learner), $result);
    }

    public function test_attempt_fails_when_assessment_not_in_course(): void
    {
        $otherCourse = Course::factory()->create();

        $this->assertFalse($this->policy->attempt($this->learner, $this->publishedAssessment, $otherCourse));
    }

    // ========== viewAttempt ==========

    public function test_lms_admin_can_view_any_attempt(): void
    {
        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertTrue($this->policy->viewAttempt($this->lmsAdmin, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_can_view_attempts_for_own_assessment(): void
    {
        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertTrue($this->policy->viewAttempt($this->contentManager, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_cannot_view_attempts_for_other_assessment(): void
    {
        $otherAssessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->otherContentManager->id,
        ]);

        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $otherAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->viewAttempt($this->contentManager, $attempt, $otherAssessment, $this->course));
    }

    public function test_learner_can_view_own_attempt(): void
    {
        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertTrue($this->policy->viewAttempt($this->learner, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_learner_cannot_view_other_attempt(): void
    {
        $otherLearner = User::factory()->create(['role' => 'learner']);
        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $otherLearner->id,
        ]);

        $this->assertFalse($this->policy->viewAttempt($this->learner, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_view_attempt_fails_when_assessment_not_in_course(): void
    {
        $otherCourse = Course::factory()->create();
        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->viewAttempt($this->lmsAdmin, $attempt, $this->publishedAssessment, $otherCourse));
    }

    public function test_view_attempt_fails_when_attempt_not_in_assessment(): void
    {
        $otherAssessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
        ]);

        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $otherAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->viewAttempt($this->lmsAdmin, $attempt, $this->publishedAssessment, $this->course));
    }

    // ========== submitAttempt ==========

    public function test_learner_can_submit_own_in_progress_attempt(): void
    {
        $attempt = AssessmentAttempt::factory()->inProgress()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertTrue($this->policy->submitAttempt($this->learner, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_learner_cannot_submit_completed_attempt(): void
    {
        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->submitAttempt($this->learner, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_learner_cannot_submit_other_attempt(): void
    {
        $otherLearner = User::factory()->create(['role' => 'learner']);
        $attempt = AssessmentAttempt::factory()->inProgress()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $otherLearner->id,
        ]);

        $this->assertFalse($this->policy->submitAttempt($this->learner, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_submit_attempt_fails_when_assessment_not_in_course(): void
    {
        $otherCourse = Course::factory()->create();
        $attempt = AssessmentAttempt::factory()->inProgress()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->submitAttempt($this->learner, $attempt, $this->publishedAssessment, $otherCourse));
    }

    // ========== grade ==========

    public function test_lms_admin_can_grade_any_attempt(): void
    {
        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertTrue($this->policy->grade($this->lmsAdmin, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_can_grade_attempts_for_own_assessment(): void
    {
        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertTrue($this->policy->grade($this->contentManager, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_content_manager_cannot_grade_attempts_for_other_assessment(): void
    {
        $otherAssessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->otherContentManager->id,
        ]);

        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $otherAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->grade($this->contentManager, $attempt, $otherAssessment, $this->course));
    }

    public function test_learner_cannot_grade_attempt(): void
    {
        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->grade($this->learner, $attempt, $this->publishedAssessment, $this->course));
    }

    public function test_grade_fails_when_assessment_not_in_course(): void
    {
        $otherCourse = Course::factory()->create();
        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $this->publishedAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->grade($this->lmsAdmin, $attempt, $this->publishedAssessment, $otherCourse));
    }

    public function test_grade_fails_when_attempt_not_in_assessment(): void
    {
        $otherAssessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
        ]);

        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $otherAssessment->id,
            'user_id' => $this->learner->id,
        ]);

        $this->assertFalse($this->policy->grade($this->lmsAdmin, $attempt, $this->publishedAssessment, $this->course));
    }
}
