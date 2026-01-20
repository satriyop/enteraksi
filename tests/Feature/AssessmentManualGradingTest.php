<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests manual grading workflow for subjective questions.
 *
 * Manual grading is required for: essay, file_upload
 *
 * Test Perspectives:
 * - Admin/ContentManager: Can I grade learner submissions?
 * - Learner: Is my manually graded score reflected correctly?
 * - Security: Only authorized users can grade
 * - State Machine: submitted → graded → completed flow
 *
 * IMPLEMENTATION NOTES:
 * - Controller methods (grade, submitGrade) need full implementation
 * - Assessment::requiresManualGrading() needs to be added to model
 */
class AssessmentManualGradingTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private User $admin;

    private User $contentManager;

    private Course $course;

    private Assessment $assessment;

    private AssessmentAttempt $attempt;

    private Question $essayQuestion;

    private AttemptAnswer $essayAnswer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['role' => 'learner']);
        $this->admin = User::factory()->create(['role' => 'lms_admin']);
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);

        $this->course = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
        ]);

        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        Lesson::factory()->create(['course_section_id' => $section->id]);

        $this->assessment = Assessment::factory()->create([
            'course_id' => $this->course->id,
            'user_id' => $this->contentManager->id,
            'status' => 'published',
            'passing_score' => 60,
        ]);

        $this->essayQuestion = Question::factory()->essay()->create([
            'assessment_id' => $this->assessment->id,
            'question_text' => 'Jelaskan pentingnya manajemen risiko dalam perbankan.',
            'points' => 100,
        ]);

        Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        $this->attempt = AssessmentAttempt::factory()->submitted()->create([
            'assessment_id' => $this->assessment->id,
            'user_id' => $this->learner->id,
            'status' => 'submitted',
        ]);

        $this->essayAnswer = AttemptAnswer::create([
            'assessment_attempt_id' => $this->attempt->id,
            'question_id' => $this->essayQuestion->id,
            'answer_text' => 'Manajemen risiko sangat penting karena dapat mencegah kerugian finansial...',
            'is_correct' => null,
            'score' => null,
        ]);
    }

    // ========== Question Type Detection (Model Tests) ==========

    public function test_essay_question_requires_manual_grading(): void
    {
        $this->assertTrue($this->essayQuestion->requiresManualGrading());
    }

    public function test_file_upload_question_requires_manual_grading(): void
    {
        $fileQuestion = Question::factory()->fileUpload()->create([
            'assessment_id' => $this->assessment->id,
        ]);

        $this->assertTrue($fileQuestion->requiresManualGrading());
    }

    public function test_multiple_choice_does_not_require_manual_grading(): void
    {
        $mcQuestion = Question::factory()->multipleChoice()->create([
            'assessment_id' => $this->assessment->id,
        ]);

        $this->assertFalse($mcQuestion->requiresManualGrading());
    }

    public function test_true_false_does_not_require_manual_grading(): void
    {
        $tfQuestion = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
        ]);

        $this->assertFalse($tfQuestion->requiresManualGrading());
    }

    public function test_short_answer_does_not_require_manual_grading(): void
    {
        $saQuestion = Question::factory()->shortAnswer()->create([
            'assessment_id' => $this->assessment->id,
        ]);

        $this->assertFalse($saQuestion->requiresManualGrading());
    }

    public function test_matching_does_not_require_manual_grading(): void
    {
        $matchQuestion = Question::factory()->matching()->create([
            'assessment_id' => $this->assessment->id,
        ]);

        $this->assertFalse($matchQuestion->requiresManualGrading());
    }

    // ========== AttemptAnswer Model Methods ==========

    public function test_answer_is_graded_returns_false_before_grading(): void
    {
        $this->assertFalse($this->essayAnswer->isGraded());
    }

    public function test_answer_is_graded_returns_true_after_grading(): void
    {
        $this->essayAnswer->update([
            'score' => 80,
            'graded_at' => now(),
            'graded_by' => $this->admin->id,
        ]);

        $this->assertTrue($this->essayAnswer->isGraded());
    }

    public function test_answer_is_correct_returns_false_when_null(): void
    {
        $this->assertFalse($this->essayAnswer->isCorrect());
    }

    public function test_answer_is_correct_returns_true_when_marked_correct(): void
    {
        $this->essayAnswer->update(['is_correct' => true]);

        $this->assertTrue($this->essayAnswer->isCorrect());
    }

    public function test_answer_is_correct_returns_false_when_marked_incorrect(): void
    {
        $this->essayAnswer->update(['is_correct' => false]);

        $this->assertFalse($this->essayAnswer->isCorrect());
    }

    public function test_answer_file_url_returns_null_when_no_file(): void
    {
        $this->assertNull($this->essayAnswer->getFileUrl());
    }

    public function test_answer_file_url_returns_url_when_file_exists(): void
    {
        $this->essayAnswer->update(['file_path' => 'assessment_answers/test.pdf']);

        $url = $this->essayAnswer->getFileUrl();

        $this->assertNotNull($url);
        $this->assertStringContains('assessment_answers/test.pdf', $url);
    }

    // ========== AssessmentAttempt Model Methods ==========

    public function test_attempt_is_in_progress_returns_true_for_in_progress(): void
    {
        $inProgressAttempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->assessment->id,
            'user_id' => $this->learner->id,
            'status' => 'in_progress',
        ]);

        $this->assertTrue($inProgressAttempt->isInProgress());
    }

    public function test_attempt_is_in_progress_returns_false_for_submitted(): void
    {
        $this->assertFalse($this->attempt->isInProgress());
    }

    public function test_attempt_is_submitted_returns_true_for_submitted(): void
    {
        $this->assertTrue($this->attempt->isSubmitted());
    }

    public function test_attempt_is_graded_returns_true_for_graded(): void
    {
        $this->attempt->update(['status' => 'graded']);

        $this->assertTrue($this->attempt->isGraded());
    }

    public function test_attempt_is_completed_returns_true_for_completed(): void
    {
        $this->attempt->update(['status' => 'completed']);

        $this->assertTrue($this->attempt->isCompleted());
    }

    public function test_attempt_requires_grading_when_submitted_but_not_graded(): void
    {
        $this->assertTrue($this->attempt->requiresGrading());
    }

    public function test_attempt_does_not_require_grading_when_already_graded(): void
    {
        $this->attempt->update(['status' => 'graded']);

        $this->assertFalse($this->attempt->requiresGrading());
    }

    public function test_attempt_does_not_require_grading_when_in_progress(): void
    {
        $this->attempt->update(['status' => 'in_progress']);

        $this->assertFalse($this->attempt->requiresGrading());
    }

    // ========== Calculate Score Method ==========

    public function test_calculate_score_updates_attempt_with_total_score(): void
    {
        // Simulate manual grading by updating answer score
        $this->essayAnswer->update([
            'score' => 80,
            'is_correct' => true,
        ]);

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        $this->assertEquals(80, $this->attempt->score);
        $this->assertEquals(100, $this->attempt->max_score);
        $this->assertEquals(80.00, $this->attempt->percentage);
        $this->assertTrue($this->attempt->passed);
        $this->assertEquals('graded', $this->attempt->status);
        $this->assertNotNull($this->attempt->graded_at);
    }

    public function test_calculate_score_determines_fail_correctly(): void
    {
        $this->essayAnswer->update([
            'score' => 50,
            'is_correct' => false,
        ]);

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        $this->assertEquals(50, $this->attempt->score);
        $this->assertEquals(50.00, $this->attempt->percentage);
        $this->assertFalse($this->attempt->passed); // 50% < 60%
    }

    public function test_calculate_score_with_multiple_answers(): void
    {
        // Add another question and answer
        $essay2 = Question::factory()->essay()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);

        $answer2 = AttemptAnswer::create([
            'assessment_attempt_id' => $this->attempt->id,
            'question_id' => $essay2->id,
            'answer_text' => 'Another answer...',
            'score' => 40,
        ]);

        $this->essayAnswer->update(['score' => 80]);

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        // Total: 80 + 40 = 120
        // Max: 100 + 50 = 150
        // Percentage: 120/150 = 80%
        $this->assertEquals(120, $this->attempt->score);
        $this->assertEquals(150, $this->attempt->max_score);
        $this->assertEquals(80.00, $this->attempt->percentage);
        $this->assertTrue($this->attempt->passed);
    }

    public function test_calculate_score_handles_zero_score(): void
    {
        $this->essayAnswer->update(['score' => 0]);

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        $this->assertEquals(0, $this->attempt->score);
        $this->assertEquals(0, $this->attempt->percentage);
        $this->assertFalse($this->attempt->passed);
    }

    // ========== Complete Attempt Method ==========

    public function test_complete_attempt_changes_status_to_completed(): void
    {
        $this->attempt->update(['status' => 'graded']);

        $this->attempt->completeAttempt();
        $this->attempt->refresh();

        $this->assertEquals('completed', $this->attempt->status);
    }

    // ========== Relationships ==========

    public function test_attempt_has_answers_relationship(): void
    {
        $answers = $this->attempt->answers;

        $this->assertCount(1, $answers);
        $this->assertEquals($this->essayAnswer->id, $answers->first()->id);
    }

    public function test_attempt_belongs_to_assessment(): void
    {
        $this->assertEquals($this->assessment->id, $this->attempt->assessment->id);
    }

    public function test_attempt_belongs_to_user(): void
    {
        $this->assertEquals($this->learner->id, $this->attempt->user->id);
    }

    public function test_answer_belongs_to_attempt(): void
    {
        $this->assertEquals($this->attempt->id, $this->essayAnswer->attempt->id);
    }

    public function test_answer_belongs_to_question(): void
    {
        $this->assertEquals($this->essayQuestion->id, $this->essayAnswer->question->id);
    }

    // ========== Graded By Relationship ==========

    public function test_attempt_graded_by_relationship(): void
    {
        $this->attempt->update([
            'graded_by' => $this->admin->id,
            'graded_at' => now(),
        ]);

        $this->assertEquals($this->admin->id, $this->attempt->gradedBy->id);
    }

    public function test_answer_graded_by_relationship(): void
    {
        $this->essayAnswer->update([
            'graded_by' => $this->admin->id,
            'graded_at' => now(),
        ]);

        $this->assertEquals($this->admin->id, $this->essayAnswer->gradedBy->id);
    }

    // ========== Mixed Auto and Manual Grading Scenarios ==========

    public function test_calculate_score_with_mixed_auto_and_manual_grades(): void
    {
        // Add a true/false question (auto-graded)
        $tfQuestion = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 20,
        ]);

        // Auto-graded answer
        AttemptAnswer::create([
            'assessment_attempt_id' => $this->attempt->id,
            'question_id' => $tfQuestion->id,
            'answer_text' => 'true',
            'is_correct' => true,
            'score' => 20,
        ]);

        // Manual-graded answer (essay)
        $this->essayAnswer->update(['score' => 80]);

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        // Total: 20 (auto) + 80 (manual) = 100
        // Max: 20 + 100 = 120
        $this->assertEquals(100, $this->attempt->score);
        $this->assertEquals(120, $this->attempt->max_score);
        // 100/120 = 83.33%
        $this->assertEquals(83.33, $this->attempt->percentage);
        $this->assertTrue($this->attempt->passed);
    }

    // ========== Edge Cases ==========

    public function test_calculate_score_with_no_answers(): void
    {
        // Delete the essay answer
        $this->essayAnswer->delete();

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        $this->assertEquals(0, $this->attempt->score);
        $this->assertEquals(100, $this->attempt->max_score); // Question still exists
        $this->assertEquals(0, $this->attempt->percentage);
        $this->assertFalse($this->attempt->passed);
    }

    public function test_calculate_score_with_null_answer_scores(): void
    {
        // Answer has null score (not yet graded)
        $this->attempt->calculateScore();
        $this->attempt->refresh();

        // NULL should be treated as 0
        $this->assertEquals(0, $this->attempt->score);
    }

    public function test_passing_score_boundary_at_exactly_60_percent(): void
    {
        $this->essayAnswer->update(['score' => 60]);

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        // 60/100 = 60% exactly equals passing_score of 60
        $this->assertEquals(60.00, $this->attempt->percentage);
        $this->assertTrue($this->attempt->passed);
    }

    public function test_passing_score_boundary_just_below(): void
    {
        $this->essayAnswer->update(['score' => 59]);

        $this->attempt->calculateScore();
        $this->attempt->refresh();

        // 59/100 = 59% < 60%
        $this->assertEquals(59.00, $this->attempt->percentage);
        $this->assertFalse($this->attempt->passed);
    }

    // ========== Helper assertion ==========
    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
