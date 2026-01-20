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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests the assessment submission process.
 *
 * Test Perspectives:
 * - Learner: Can I submit my answers correctly?
 * - Security: Can I only submit my own attempt?
 * - Data Integrity: Are answers persisted correctly?
 * - State Machine: Does status transition correctly on submission?
 */
class AssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private User $admin;

    private Course $course;

    private Assessment $assessment;

    private AssessmentAttempt $attempt;

    private Question $mcQuestion;

    private Question $tfQuestion;

    private Question $essayQuestion;

    private QuestionOption $correctOption;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->learner = User::factory()->create(['role' => 'learner']);
        $this->admin = User::factory()->create(['role' => 'lms_admin']);

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
            'passing_score' => 60,
            'max_attempts' => 3,
        ]);

        // Create diverse question types
        $this->createQuestions();

        Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        // Create an in-progress attempt
        $this->attempt = AssessmentAttempt::factory()->create([
            'assessment_id' => $this->assessment->id,
            'user_id' => $this->learner->id,
            'status' => 'in_progress',
            'attempt_number' => 1,
        ]);
    }

    private function createQuestions(): void
    {
        // Multiple choice (10 points)
        $this->mcQuestion = Question::factory()->multipleChoice()->create([
            'assessment_id' => $this->assessment->id,
            'question_text' => 'Apa ibu kota Indonesia?',
            'points' => 10,
            'order' => 1,
        ]);

        $this->correctOption = QuestionOption::factory()->correct()->create([
            'question_id' => $this->mcQuestion->id,
            'option_text' => 'Jakarta',
            'order' => 1,
        ]);

        QuestionOption::factory()->incorrect()->create([
            'question_id' => $this->mcQuestion->id,
            'option_text' => 'Surabaya',
            'order' => 2,
        ]);

        // True/False (5 points)
        $this->tfQuestion = Question::factory()->trueFalse()->create([
            'assessment_id' => $this->assessment->id,
            'question_text' => 'Indonesia adalah negara kepulauan. (Benar/Salah)',
            'points' => 5,
            'order' => 2,
        ]);

        // Essay (15 points) - requires manual grading
        $this->essayQuestion = Question::factory()->essay()->create([
            'assessment_id' => $this->assessment->id,
            'question_text' => 'Jelaskan pentingnya compliance dalam industri perbankan.',
            'points' => 15,
            'order' => 3,
        ]);
    }

    // ========== Basic Submission ==========

    public function test_learner_can_submit_in_progress_attempt(): void
    {
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $this->mcQuestion->id,
                        'answer_text' => $this->correctOption->option_text,
                    ],
                    [
                        'question_id' => $this->tfQuestion->id,
                        'answer_text' => 'benar',
                    ],
                    [
                        'question_id' => $this->essayQuestion->id,
                        'answer_text' => 'Compliance sangat penting karena...',
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->attempt->refresh();

        // Should be 'submitted' because essay requires manual grading
        $this->assertEquals('submitted', $this->attempt->status);
        $this->assertNotNull($this->attempt->submitted_at);
    }

    public function test_submission_creates_answer_records(): void
    {
        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $this->mcQuestion->id,
                        'answer_text' => 'Jakarta',
                    ],
                    [
                        'question_id' => $this->tfQuestion->id,
                        'answer_text' => 'benar',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('attempt_answers', [
            'assessment_attempt_id' => $this->attempt->id,
            'question_id' => $this->mcQuestion->id,
            'answer_text' => 'Jakarta',
        ]);

        $this->assertDatabaseHas('attempt_answers', [
            'assessment_attempt_id' => $this->attempt->id,
            'question_id' => $this->tfQuestion->id,
            'answer_text' => 'benar',
        ]);
    }

    public function test_submitted_at_timestamp_is_recorded(): void
    {
        $beforeSubmit = now();

        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        $this->attempt->refresh();

        $this->assertNotNull($this->attempt->submitted_at);
        $this->assertTrue($this->attempt->submitted_at->gte($beforeSubmit));
    }

    // ========== Submission Validation ==========

    public function test_answers_array_is_required(): void
    {
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                // No answers provided
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('answers');
    }

    public function test_question_id_must_exist(): void
    {
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id' => 99999, // Non-existent
                        'answer_text' => 'Some answer',
                    ],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_partial_submission_is_allowed(): void
    {
        // Submit only some answers
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $this->mcQuestion->id,
                        'answer_text' => 'Jakarta',
                    ],
                    // Skipping other questions
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Only one answer should be recorded
        $answerCount = AttemptAnswer::where('assessment_attempt_id', $this->attempt->id)->count();
        $this->assertEquals(1, $answerCount);
    }

    // ========== Authorization ==========

    public function test_guest_cannot_submit_attempt(): void
    {
        $response = $this->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
            'answers' => [
                ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
            ],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_learner_cannot_submit_another_users_attempt(): void
    {
        $otherLearner = User::factory()->create(['role' => 'learner']);
        Enrollment::factory()->create([
            'user_id' => $otherLearner->id,
            'course_id' => $this->course->id,
        ]);

        $response = $this->actingAs($otherLearner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_submit_on_behalf_of_learner(): void
    {
        // Even admin shouldn't be able to submit for someone else
        $response = $this->actingAs($this->admin)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        // Policy checks attempt belongs to user
        $response->assertForbidden();
    }

    // ========== State Transition Validation ==========

    public function test_cannot_submit_already_submitted_attempt(): void
    {
        $this->attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_submit_graded_attempt(): void
    {
        $this->attempt->update([
            'status' => 'graded',
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_submit_completed_attempt(): void
    {
        $this->attempt->update([
            'status' => 'completed',
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        $response->assertForbidden();
    }

    // ========== File Upload Answers ==========

    public function test_can_submit_file_upload_answer(): void
    {
        $fileQuestion = Question::factory()->fileUpload()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
            'order' => 4,
        ]);

        $file = UploadedFile::fake()->create('assignment.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $fileQuestion->id,
                        'answer_text' => null,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $answer = AttemptAnswer::where('assessment_attempt_id', $this->attempt->id)
            ->where('question_id', $fileQuestion->id)
            ->first();

        $this->assertNotNull($answer);
        $this->assertNotNull($answer->file_path);
        Storage::disk('public')->assertExists($answer->file_path);
    }

    public function test_file_upload_validates_max_size(): void
    {
        $fileQuestion = Question::factory()->fileUpload()->create([
            'assessment_id' => $this->assessment->id,
            'points' => 10,
        ]);

        // Create a file larger than 10MB limit
        $file = UploadedFile::fake()->create('large_file.pdf', 15000, 'application/pdf'); // 15MB

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $fileQuestion->id,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertUnprocessable();
    }

    // ========== Cross-Entity Validation ==========

    public function test_cannot_submit_through_wrong_course(): void
    {
        $otherCourse = Course::factory()->published()->create();

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$otherCourse->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_submit_attempt_for_different_assessment(): void
    {
        $otherAssessment = Assessment::factory()->create([
            'course_id' => $this->course->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$otherAssessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        $response->assertForbidden();
    }

    // ========== Completion Page ==========

    public function test_learner_can_view_completion_page_after_submission(): void
    {
        // Submit the attempt first
        $this->actingAs($this->learner)
            ->post("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $this->mcQuestion->id, 'answer_text' => 'Jakarta'],
                ],
            ]);

        // View completion page
        $response = $this->actingAs($this->learner)
            ->get("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/complete");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('assessments/AttemptComplete')
            ->has('attempt')
            ->has('assessment')
            ->has('course')
        );
    }

    public function test_guest_cannot_view_completion_page(): void
    {
        $this->attempt->update(['status' => 'submitted']);

        $response = $this->get("/courses/{$this->course->id}/assessments/{$this->assessment->id}/attempts/{$this->attempt->id}/complete");

        $response->assertRedirect(route('login'));
    }
}
