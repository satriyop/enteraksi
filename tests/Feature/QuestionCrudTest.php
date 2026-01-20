<?php
namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_managers_can_create_questions(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'What is 2 + 2?',
                'question_type' => 'multiple_choice',
                'points'        => 1,
                'options'       => [
                    ['option_text' => '3', 'is_correct' => false],
                    ['option_text' => '4', 'is_correct' => true],
                    ['option_text' => '5', 'is_correct' => false],
                ],
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questions', [
            'question_text' => 'What is 2 + 2?',
            'assessment_id' => $assessment->id,
        ]);
        $this->assertDatabaseCount('question_options', 3);
    }

    public function test_content_managers_can_update_questions(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);
        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'multiple_choice',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'id'            => $question->id,
                'question_text' => 'Updated question text',
                'question_type' => 'multiple_choice',
                'points'        => 2,
                'options'       => [
                    ['option_text' => 'Option 1', 'is_correct' => true],
                    ['option_text' => 'Option 2', 'is_correct' => false],
                ],
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questions', [
            'id'            => $question->id,
            'question_text' => 'Updated question text',
            'points'        => 2,
        ]);
    }

    public function test_content_managers_can_delete_questions(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);
        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
        ]);

        $response = $this->actingAs($user)->delete("/courses/{$course->id}/assessments/{$assessment->id}/questions/{$question->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('questions', ['id' => $question->id]);
    }

    public function test_questions_can_have_options(): void
    {
        $question = Question::factory()->create([
            'question_type' => 'multiple_choice',
        ]);
        $options = QuestionOption::factory()->count(4)->create([
            'question_id' => $question->id,
        ]);

        $this->assertCount(4, $question->options);
        $this->assertEquals($options->first()->option_text, $question->options->first()->option_text);
    }

    public function test_question_validation_requires_text(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => '',
                'question_type' => 'multiple_choice',
                'points'        => 1,
            ]],
        ]);

        $response->assertSessionHasErrors(['questions.0.question_text']);
    }

    public function test_question_validation_requires_valid_type(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Test question',
                'question_type' => 'invalid_type',
                'points'        => 1,
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['questions.0.question_type']);
    }

    public function test_multiple_choice_questions_require_options(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Test question',
                'question_type' => 'multiple_choice',
                'points'        => 1,
                'options'       => [],
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['questions.0.options']);
    }

    public function test_question_points_must_be_positive(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Test question',
                'question_type' => 'short_answer',
                'points'        => 0,
            ]],
        ]);

        $response->assertSessionHasErrors(['questions.0.points']);
    }

    public function test_true_false_question_creation(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Is the sky blue?',
                'question_type' => 'true_false',
                'points'        => 1,
                'options'       => [
                    ['option_text' => 'True', 'is_correct' => true],
                    ['option_text' => 'False', 'is_correct' => false],
                ],
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questions', [
            'question_text' => 'Is the sky blue?',
            'question_type' => 'true_false',
            'assessment_id' => $assessment->id,
        ]);
        $this->assertDatabaseCount('question_options', 2);
    }

    public function test_short_answer_question_creation(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'What is the capital of France?',
                'question_type' => 'short_answer',
                'points'        => 2,
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questions', [
            'question_text' => 'What is the capital of France?',
            'question_type' => 'short_answer',
            'assessment_id' => $assessment->id,
        ]);
        $this->assertDatabaseCount('question_options', 0);
    }

    public function test_essay_question_creation(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Describe the causes of World War II in detail.',
                'question_type' => 'essay',
                'points'        => 10,
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questions', [
            'question_text' => 'Describe the causes of World War II in detail.',
            'question_type' => 'essay',
            'assessment_id' => $assessment->id,
        ]);
        $this->assertDatabaseCount('question_options', 0);
    }

    public function test_file_upload_question_creation(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Upload your project proposal document.',
                'question_type' => 'file_upload',
                'points'        => 15,
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questions', [
            'question_text' => 'Upload your project proposal document.',
            'question_type' => 'file_upload',
            'assessment_id' => $assessment->id,
        ]);
        $this->assertDatabaseCount('question_options', 0);
    }

    public function test_matching_question_creation(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Match the terms with their definitions.',
                'question_type' => 'matching',
                'points'        => 5,
                'options'       => [
                    ['option_text' => 'Term 1', 'is_correct' => false, 'match_text' => 'Definition 1'],
                    ['option_text' => 'Term 2', 'is_correct' => false, 'match_text' => 'Definition 2'],
                    ['option_text' => 'Term 3', 'is_correct' => false, 'match_text' => 'Definition 3'],
                ],
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questions', [
            'question_text' => 'Match the terms with their definitions.',
            'question_type' => 'matching',
            'assessment_id' => $assessment->id,
        ]);
        $this->assertDatabaseCount('question_options', 3);
    }

    public function test_question_type_methods_work_correctly(): void
    {
        $multipleChoice = Question::factory()->multipleChoice()->create();
        $trueFalse      = Question::factory()->trueFalse()->create();
        $shortAnswer    = Question::factory()->shortAnswer()->create();
        $essay          = Question::factory()->essay()->create();
        $fileUpload     = Question::factory()->fileUpload()->create();
        $matching       = Question::factory()->matching()->create();

        $this->assertTrue($multipleChoice->isMultipleChoice());
        $this->assertFalse($multipleChoice->isTrueFalse());

        $this->assertTrue($trueFalse->isTrueFalse());
        $this->assertFalse($trueFalse->isShortAnswer());

        $this->assertTrue($shortAnswer->isShortAnswer());
        $this->assertFalse($shortAnswer->isEssay());

        $this->assertTrue($essay->isEssay());
        $this->assertFalse($essay->isFileUpload());

        $this->assertTrue($fileUpload->isFileUpload());
        $this->assertFalse($fileUpload->isMatching());

        $this->assertTrue($matching->isMatching());
        $this->assertFalse($matching->isMultipleChoice());
    }

    public function test_manual_grading_requirement_for_question_types(): void
    {
        $essay          = Question::factory()->essay()->create();
        $fileUpload     = Question::factory()->fileUpload()->create();
        $multipleChoice = Question::factory()->multipleChoice()->create();
        $shortAnswer    = Question::factory()->shortAnswer()->create();

        $this->assertTrue($essay->requiresManualGrading());
        $this->assertTrue($fileUpload->requiresManualGrading());
        $this->assertFalse($multipleChoice->requiresManualGrading());
        $this->assertFalse($shortAnswer->requiresManualGrading());
    }

    public function test_question_type_labels_are_correct(): void
    {
        $multipleChoice = Question::factory()->multipleChoice()->create();
        $trueFalse      = Question::factory()->trueFalse()->create();
        $matching       = Question::factory()->matching()->create();
        $shortAnswer    = Question::factory()->shortAnswer()->create();
        $essay          = Question::factory()->essay()->create();
        $fileUpload     = Question::factory()->fileUpload()->create();

        $this->assertEquals('Pilihan Ganda', $multipleChoice->getQuestionTypeLabel());
        $this->assertEquals('Benar/Salah', $trueFalse->getQuestionTypeLabel());
        $this->assertEquals('Pencocokan', $matching->getQuestionTypeLabel());
        $this->assertEquals('Jawaban Singkat', $shortAnswer->getQuestionTypeLabel());
        $this->assertEquals('Esai', $essay->getQuestionTypeLabel());
        $this->assertEquals('Unggah Berkas', $fileUpload->getQuestionTypeLabel());
    }

    public function test_question_with_multiple_correct_options(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}/questions", [
            'questions' => [[
                'question_text' => 'Select all that apply: Which of these are programming languages?',
                'question_type' => 'multiple_choice',
                'points'        => 3,
                'options'       => [
                    ['option_text' => 'PHP', 'is_correct' => true],
                    ['option_text' => 'JavaScript', 'is_correct' => true],
                    ['option_text' => 'HTML', 'is_correct' => false],
                    ['option_text' => 'Python', 'is_correct' => true],
                ],
            ]],
        ]);

        $response->assertRedirect();
        $question = Question::where('question_text', 'Select all that apply: Which of these are programming languages?')->first();
        $this->assertEquals(3, $question->getCorrectOptions()->count());
    }
}