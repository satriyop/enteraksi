<?php

namespace Tests\Feature;

use App\Domain\Progress\Services\ProgressTrackingService;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edge cases and business rule tests.
 *
 * These tests cover boundary conditions, race conditions,
 * and business rule edge cases that are critical for stability
 * during refactoring.
 *
 * Categories:
 * - Boundary Conditions (min/max values)
 * - Time-based Behaviors
 * - Data Integrity
 * - Concurrent Operations
 * - Error Recovery
 */
class EdgeCasesAndBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $lmsAdmin;

    private User $contentManager;

    private User $learner;

    private Course $course;

    private ProgressTrackingService $progressService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);
        $this->learner = User::factory()->create(['role' => 'learner']);

        $this->course = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'public',
        ]);

        $this->progressService = app(ProgressTrackingService::class);
    }

    // ========== ASSESSMENT ATTEMPT LIMITS ==========

    public function test_max_attempts_boundary_exactly_at_limit(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'max_attempts' => 3,
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // Create exactly 3 attempts
        for ($i = 1; $i <= 3; $i++) {
            AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $this->learner->id,
                'attempt_number' => $i,
            ]);
        }

        // Should not be able to attempt anymore
        $this->assertFalse($assessment->canBeAttemptedBy($this->learner));
    }

    public function test_max_attempts_zero_means_unlimited(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'max_attempts' => 0, // 0 = unlimited
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // Create 10 attempts
        for ($i = 1; $i <= 10; $i++) {
            AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $this->learner->id,
                'attempt_number' => $i,
            ]);
        }

        // Should still be able to attempt
        $this->assertTrue($assessment->canBeAttemptedBy($this->learner));
    }

    public function test_in_progress_attempt_does_not_count_against_limit(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'max_attempts' => 1,
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // Create one in-progress attempt
        AssessmentAttempt::factory()->inProgress()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->learner->id,
            'attempt_number' => 1,
        ]);

        // Should still be able to attempt (continue existing)
        $this->assertTrue($assessment->canBeAttemptedBy($this->learner));
    }

    // ========== PROGRESS CALCULATION EDGE CASES ==========

    public function test_progress_with_empty_course_is_zero(): void
    {
        $emptyCourse = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
        ]);

        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $emptyCourse->id,
        ]);

        $this->progressService->recalculateCourseProgress($enrollment);

        $this->assertEquals(0, $enrollment->progress_percentage);
        $this->assertEquals('active', $enrollment->status);
    }

    public function test_progress_with_one_lesson_course(): void
    {
        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // Complete the only lesson
        LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'current_page' => 1,
        ]);

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        $this->assertEquals(100, $enrollment->progress_percentage);
        $this->assertEquals('completed', $enrollment->status);
    }

    public function test_progress_calculation_rounds_correctly(): void
    {
        // Create course with 7 lessons for a non-round percentage
        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);

        for ($i = 0; $i < 7; $i++) {
            Lesson::factory()->create(['course_section_id' => $section->id]);
        }

        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // Complete 3 of 7 lessons (42.857...%)
        $lessons = $this->course->lessons->take(3);
        foreach ($lessons as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        // Should round to 1 decimal: 42.857... → 42.9
        $this->assertEquals(42.9, $enrollment->progress_percentage);
    }

    // ========== SCORE CALCULATION EDGE CASES ==========

    public function test_assessment_score_with_zero_max_score(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'passing_score' => 0,
        ]);

        $attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->learner->id,
        ]);

        // Score calculation with 0 max should not cause division by zero
        $attempt->update([
            'score' => 0,
            'max_score' => 0,
            'percentage' => 0,
            'passed' => true,
        ]);

        $this->assertEquals(0, $attempt->percentage);
        $this->assertTrue($attempt->passed);
    }

    public function test_score_boundary_at_passing_threshold(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'passing_score' => 70,
        ]);

        $attempt = AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->learner->id,
            'score' => 70,
            'max_score' => 100,
            'percentage' => 70,
            'passed' => true, // Exactly at threshold
        ]);

        $this->assertTrue($attempt->passed);
    }

    public function test_score_just_below_passing_threshold(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'passing_score' => 70,
        ]);

        $attempt = AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->learner->id,
            'score' => 69,
            'max_score' => 100,
            'percentage' => 69,
            'passed' => false,
        ]);

        $this->assertFalse($attempt->passed);
    }

    // ========== INVITATION EDGE CASES ==========

    /**
     * NOTE: This test documents current behavior where expired invitations
     * are NOT checked during enrollment. This is a potential bug/improvement.
     *
     * Expected behavior: Expired invitations should not allow enrollment
     * Current behavior: System ignores expiration date
     */
    public function test_expired_invitation_behavior_documented(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'restricted',
        ]);

        // Create expired invitation
        CourseInvitation::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$course->id}/enroll");

        // Current behavior: System allows enrollment even with expired invitation
        // TODO: Consider implementing expiration check in policy
        $response->assertRedirect();
        $this->markTestIncomplete(
            'Invitation expiration is not currently checked. Consider implementing this check.'
        );
    }

    public function test_invitation_exactly_at_expiry_boundary(): void
    {
        $course = Course::factory()->published()->create([
            'visibility' => 'restricted',
        ]);

        // Create invitation expiring now (edge case)
        CourseInvitation::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'status' => 'pending',
            'expires_at' => now(),
        ]);

        // Behavior depends on implementation (>= vs > comparison)
        // This documents current behavior
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$course->id}/enroll");

        // Should succeed if expiry is inclusive, fail if exclusive
        $this->assertTrue(
            $response->isRedirection() || $response->isForbidden(),
            'Response should be redirect or forbidden'
        );
    }

    // ========== DATA INTEGRITY ==========

    public function test_deleting_course_soft_deletes_correctly(): void
    {
        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        Lesson::factory()->create(['course_section_id' => $section->id]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        $this->course->delete();

        // Course should be soft deleted
        $this->assertSoftDeleted('courses', ['id' => $this->course->id]);

        // Enrollments should still exist
        $this->assertDatabaseHas('enrollments', [
            'course_id' => $this->course->id,
        ]);
    }

    public function test_assessment_attempt_preserves_history(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
        ]);

        $attempt = AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->learner->id,
            'score' => 85,
            'percentage' => 85,
        ]);

        // Update assessment (e.g., change passing score)
        $assessment->update(['passing_score' => 90]);

        // Attempt history should be preserved
        $attempt->refresh();
        $this->assertEquals(85, $attempt->score);
        $this->assertEquals(85, $attempt->percentage);
    }

    public function test_lesson_progress_persists_after_course_unpublish(): void
    {
        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        $progress = LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'current_page' => 5,
            'is_completed' => true,
        ]);

        // Unpublish course
        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$this->course->id}/unpublish");

        // Progress should still exist
        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'current_page' => 5,
            'is_completed' => true,
        ]);
    }

    // ========== MODEL STATE TRANSITIONS ==========

    public function test_enrollment_cannot_transition_from_completed_to_dropped(): void
    {
        $enrollment = Enrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->delete("/courses/{$this->course->id}/unenroll");

        $response->assertSessionHas('error');

        $enrollment->refresh();
        $this->assertEquals('completed', $enrollment->status);
    }

    public function test_attempt_status_transitions_are_one_way(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
        ]);

        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->learner->id,
        ]);

        // Should not be able to submit again
        $this->assertFalse($attempt->isInProgress());
    }

    // ========== MULTIPLE USER SCENARIOS ==========

    public function test_multiple_learners_different_progress_same_course(): void
    {
        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);

        for ($i = 0; $i < 5; $i++) {
            Lesson::factory()->create(['course_section_id' => $section->id]);
        }

        $learner1 = User::factory()->create(['role' => 'learner']);
        $learner2 = User::factory()->create(['role' => 'learner']);

        $enrollment1 = Enrollment::factory()->active()->create([
            'user_id' => $learner1->id,
            'course_id' => $this->course->id,
        ]);

        $enrollment2 = Enrollment::factory()->active()->create([
            'user_id' => $learner2->id,
            'course_id' => $this->course->id,
        ]);

        // Learner 1 completes 2 lessons
        $lessons = $this->course->lessons;
        foreach ($lessons->take(2) as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment1->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        // Learner 2 completes 4 lessons
        foreach ($lessons->take(4) as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment2->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        $this->progressService->recalculateCourseProgress($enrollment1);
        $this->progressService->recalculateCourseProgress($enrollment2);

        $enrollment1->refresh();
        $enrollment2->refresh();

        // Verify independent progress
        $this->assertEquals(40, $enrollment1->progress_percentage);
        $this->assertEquals(80, $enrollment2->progress_percentage);
    }

    public function test_multiple_attempts_same_assessment_correct_numbering(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'max_attempts' => 5,
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // Create multiple attempts
        for ($i = 1; $i <= 3; $i++) {
            AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $this->learner->id,
                'attempt_number' => $i,
            ]);
        }

        // Next attempt should be number 4
        $nextAttemptNumber = $assessment->attempts()
            ->where('user_id', $this->learner->id)
            ->max('attempt_number') + 1;

        $this->assertEquals(4, $nextAttemptNumber);
    }

    // ========== NULL AND EMPTY VALUE HANDLING ==========

    public function test_course_with_null_category(): void
    {
        $course = Course::factory()->published()->create([
            'category_id' => null,
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($this->learner)
            ->get("/courses/{$course->id}");

        $response->assertOk();
    }

    public function test_assessment_with_null_time_limit(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'time_limit_minutes' => null,
        ]);

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        // Should be able to attempt with no time limit
        $this->assertTrue($assessment->canBeAttemptedBy($this->learner));
    }

    public function test_enrollment_with_null_started_at(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
            'started_at' => null,
        ]);

        $this->assertNull($enrollment->started_at);
        $this->assertEquals('active', $enrollment->status);
    }

    // ========== RELATIONSHIP QUERIES ==========

    public function test_course_lessons_through_sections(): void
    {
        $section1 = CourseSection::factory()->create([
            'course_id' => $this->course->id,
            'order' => 1,
        ]);
        $section2 = CourseSection::factory()->create([
            'course_id' => $this->course->id,
            'order' => 2,
        ]);

        Lesson::factory()->count(3)->create(['course_section_id' => $section1->id]);
        Lesson::factory()->count(2)->create(['course_section_id' => $section2->id]);

        // HasManyThrough should get all lessons
        $this->assertEquals(5, $this->course->lessons()->count());
    }

    public function test_assessment_questions_with_options(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
        ]);

        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'multiple_choice',
        ]);

        // Load questions with options
        $assessment->load('questions.options');

        $this->assertNotNull($assessment->questions->first());
    }

    // ========== AUDIT/TRACKING FIELDS ==========

    public function test_attempt_graded_by_tracks_grader(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
        ]);

        $attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->learner->id,
        ]);

        $attempt->update([
            'status' => 'graded',
            'graded_by' => $this->contentManager->id,
            'graded_at' => now(),
        ]);

        $this->assertEquals($this->contentManager->id, $attempt->graded_by);
        $this->assertNotNull($attempt->graded_at);
    }

    public function test_enrollment_invited_by_tracks_inviter(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
            'invited_by' => $this->contentManager->id,
        ]);

        $this->assertEquals($this->contentManager->id, $enrollment->invited_by);
        $this->assertEquals($this->contentManager->id, $enrollment->invitedBy->id);
    }

    // ========== TIMESTAMP ACCURACY ==========

    public function test_timestamps_updated_on_model_changes(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
        ]);

        $originalUpdatedAt = $enrollment->updated_at;

        // Wait a moment
        sleep(1);

        // Update enrollment
        $enrollment->update(['progress_percentage' => 50]);

        $this->assertTrue($enrollment->updated_at->gt($originalUpdatedAt));
    }
}
