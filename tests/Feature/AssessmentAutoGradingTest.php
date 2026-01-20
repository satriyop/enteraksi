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
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests automatic grading for objective question types.
 *
 * Auto-gradable types: multiple_choice, true_false, matching, short_answer
 * Manual grading types: essay, file_upload
 *
 * Test Perspectives:
 * - Learner: Is my score calculated correctly?
 * - Data Integrity: Are scores and percentages accurate?
 * - Business Rules: Is pass/fail determined correctly?
 */
class AssessmentAutoGradingTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private Course $course;

    private Assessment $assessment;

    private AssessmentAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['role' => 'learner']);
        $contentManager = User::factory()->create(['role' => 'content_manager']);

        $this->course = Course::factory()->published()->create([
            'user_id' => $contentManager->id,
        ]);

        $section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        Lesson::factory()->create(['course_section_id' => $section->id]);

        $this->assessment = Assessment::factory()->create([
            'course_id' => $this->course->id,
            'user_id' => $contentManager->id,
            'status' => 'published',
            'passing_score' => 70,
        ]);

        Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        $this->attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->assessment->id,
            'user_id' => $this->learner->id,
            'status' => 'in_progress',
        ]);
    }

    // ========== True/False Auto-Grading ==========

    public function test_true_false_correct_answer_scores_full_points(): void
    {
        $question = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $question->id, 'answer_text' => 'true'],
                ],
            ]);

        $answer = AttemptAnswer::where('assessment_attempt_id', $this->attempt->id)
            ->where('question_id', $question->id)
            ->first();

        // Note: Current implementation checks for 'true' or 'benar'
        $this->assertTrue($answer->is_correct);
        $this->assertEquals(10, $answer->score);
    }

    public function test_true_false_benar_is_accepted_as_true(): void
    {
        $question = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $question->id, 'answer_text' => 'benar'],
                ],
            ]);

        $answer = AttemptAnswer::where('assessment_attempt_id', $this->attempt->id)
            ->where('question_id', $question->id)
            ->first();

        $this->assertTrue($answer->is_correct);
        $this->assertEquals(10, $answer->score);
    }

    public function test_true_false_incorrect_answer_scores_zero(): void
    {
        $question = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $question->id, 'answer_text' => 'false'],
                ],
            ]);

        $answer = AttemptAnswer::where('assessment_attempt_id', $this->attempt->id)
            ->where('question_id', $question->id)
            ->first();

        $this->assertFalse($answer->is_correct);
        $this->assertEquals(0, $answer->score);
    }

    public function test_true_false_case_insensitive(): void
    {
        $question = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $question->id, 'answer_text' => 'TRUE'],
                ],
            ]);

        $answer = AttemptAnswer::where('assessment_attempt_id', $this->attempt->id)
            ->where('question_id', $question->id)
            ->first();

        $this->assertTrue($answer->is_correct);
    }

    public function test_true_false_trims_whitespace(): void
    {
        $question = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $question->id, 'answer_text' => '  true  '],
                ],
            ]);

        $answer = AttemptAnswer::where('assessment_attempt_id', $this->attempt->id)
            ->where('question_id', $question->id)
            ->first();

        $this->assertTrue($answer->is_correct);
    }

    // ========== Score Calculation ==========

    public function test_total_score_calculated_correctly(): void
    {
        // Create 3 true/false questions
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
            'order' => 1,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
            'order' => 2,
        ]);
        $q3 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
            'order' => 3,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'], // Correct (10)
                    ['question_id' => $q2->id, 'answer_text' => 'true'], // Correct (10)
                    ['question_id' => $q3->id, 'answer_text' => 'false'], // Incorrect (0)
                ],
            ]);

        $this->attempt->refresh();

        $this->assertEquals(20, $this->attempt->score); // 10 + 10 + 0
        $this->assertEquals(30, $this->attempt->max_score);
    }

    public function test_percentage_calculated_correctly(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 25,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 75,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'], // 25 points
                    ['question_id' => $q2->id, 'answer_text' => 'false'], // 0 points
                ],
            ]);

        $this->attempt->refresh();

        // 25 out of 100 = 25%
        $this->assertEquals(25.00, $this->attempt->percentage);
    }

    public function test_pass_status_when_meeting_passing_score(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 70,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 30,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'], // 70 points
                    ['question_id' => $q2->id, 'answer_text' => 'false'], // 0 points
                ],
            ]);

        $this->attempt->refresh();

        // 70% with passing score of 70%
        $this->assertEquals(70.00, $this->attempt->percentage);
        $this->assertTrue($this->attempt->passed);
    }

    public function test_fail_status_when_below_passing_score(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'], // 50 points
                    ['question_id' => $q2->id, 'answer_text' => 'false'], // 0 points
                ],
            ]);

        $this->attempt->refresh();

        // 50% with passing score of 70%
        $this->assertEquals(50.00, $this->attempt->percentage);
        $this->assertFalse($this->attempt->passed);
    }

    // ========== Status After Grading ==========

    public function test_status_is_graded_when_all_questions_auto_gradable(): void
    {
        // Only auto-gradable questions
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'],
                ],
            ]);

        $this->attempt->refresh();

        $this->assertEquals('graded', $this->attempt->status);
    }

    public function test_status_is_submitted_when_manual_grading_required(): void
    {
        // Mix of auto and manual grading questions
        $tfQuestion = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);
        $essayQuestion = Question::factory()->essay()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $tfQuestion->id, 'answer_text' => 'true'],
                    ['question_id' => $essayQuestion->id, 'answer_text' => 'Essay answer...'],
                ],
            ]);

        $this->attempt->refresh();

        // Should be 'submitted' because essay requires manual grading
        $this->assertEquals('submitted', $this->attempt->status);
    }

    // ========== Edge Cases ==========

    public function test_zero_points_assessment_handles_percentage(): void
    {
        // Assessment with no questions (0 total points)
        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [],
            ]);

        $this->attempt->refresh();

        // Should not divide by zero
        $this->assertEquals(0, $this->attempt->percentage);
    }

    public function test_unanswered_questions_score_zero(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);

        // Only answer one question
        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'],
                    // q2 not answered
                ],
            ]);

        $this->attempt->refresh();

        // Only q1's 50 points counted
        $this->assertEquals(50, $this->attempt->score);
        // Max score still includes all questions
        $this->assertEquals(100, $this->attempt->max_score);
        $this->assertEquals(50.00, $this->attempt->percentage);
    }

    public function test_percentage_rounds_to_two_decimal_places(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 33,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 33,
        ]);
        $q3 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 34,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'], // 33 points
                    ['question_id' => $q2->id, 'answer_text' => 'false'], // 0 points
                    ['question_id' => $q3->id, 'answer_text' => 'false'], // 0 points
                ],
            ]);

        $this->attempt->refresh();

        // 33/100 = 33%
        $this->assertEquals(33.00, $this->attempt->percentage);
    }

    // ========== AssessmentAttempt Model calculateScore Method ==========

    public function test_calculate_score_method_updates_attempt_correctly(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 40,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 60,
        ]);

        // Create answers directly
        AttemptAnswer::create([
            'assessment_attempt_id' => $this->attempt->id,
            'question_id' => $q1->id,
            'answer_text' => 'true',
            'is_correct' => true,
            'score' => 40,
        ]);
        AttemptAnswer::create([
            'assessment_attempt_id' => $this->attempt->id,
            'question_id' => $q2->id,
            'answer_text' => 'false',
            'is_correct' => false,
            'score' => 0,
        ]);

        // Call calculateScore method
        $this->attempt->calculateScore();
        $this->attempt->refresh();

        $this->assertEquals(40, $this->attempt->score);
        $this->assertEquals(100, $this->attempt->max_score);
        $this->assertEquals(40.00, $this->attempt->percentage);
        $this->assertFalse($this->attempt->passed); // 40% < 70%
        $this->assertEquals('graded', $this->attempt->status);
        $this->assertNotNull($this->attempt->graded_at);
    }

    // ========== Perfect Score ==========

    public function test_perfect_score_results_in_100_percent(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'true'],
                    ['question_id' => $q2->id, 'answer_text' => 'true'],
                ],
            ]);

        $this->attempt->refresh();

        $this->assertEquals(100, $this->attempt->score);
        $this->assertEquals(100, $this->attempt->max_score);
        $this->assertEquals(100.00, $this->attempt->percentage);
        $this->assertTrue($this->attempt->passed);
    }

    // ========== Zero Score ==========

    public function test_all_wrong_answers_result_in_zero_percent(): void
    {
        $q1 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);
        $q2 = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 50,
        ]);

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer_text' => 'false'], // wrong
                    ['question_id' => $q2->id, 'answer_text' => 'false'], // wrong
                ],
            ]);

        $this->attempt->refresh();

        $this->assertEquals(0, $this->attempt->score);
        $this->assertEquals(100, $this->attempt->max_score);
        $this->assertEquals(0, $this->attempt->percentage);
        $this->assertFalse($this->attempt->passed);
    }
}
