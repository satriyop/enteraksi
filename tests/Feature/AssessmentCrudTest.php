<?php
namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_managers_can_create_assessments(): void
    {
        $user   = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/courses/{$course->id}/assessments", [
            'title'                => 'Test Assessment',
            'description'          => 'This is a test assessment',
            'passing_score'        => 70,
            'max_attempts'         => 2,
            'shuffle_questions'    => false,
            'show_correct_answers' => true,
            'allow_review'         => true,
            'status'               => 'draft',
            'visibility'           => 'public',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'title'     => 'Test Assessment',
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);
    }

    public function test_content_managers_can_update_their_own_assessments(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($user)->put("/courses/{$course->id}/assessments/{$assessment->id}", [
            'title'                => 'Updated Assessment Title',
            'description'          => $assessment->description,
            'passing_score'        => $assessment->passing_score,
            'max_attempts'         => $assessment->max_attempts,
            'shuffle_questions'    => $assessment->shuffle_questions,
            'show_correct_answers' => $assessment->show_correct_answers,
            'allow_review'         => $assessment->allow_review,
            'status'               => $assessment->status,
            'visibility'           => $assessment->visibility,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'id'    => $assessment->id,
            'title' => 'Updated Assessment Title',
        ]);
    }

    public function test_content_managers_cannot_update_others_assessments(): void
    {
        $owner      = User::factory()->create(['role' => 'content_manager']);
        $other      = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $owner->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $owner->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($other)->put("/courses/{$course->id}/assessments/{$assessment->id}", [
            'title'                => 'Hijacked Title',
            'description'          => $assessment->description,
            'passing_score'        => $assessment->passing_score,
            'max_attempts'         => $assessment->max_attempts,
            'shuffle_questions'    => $assessment->shuffle_questions,
            'show_correct_answers' => $assessment->show_correct_answers,
            'allow_review'         => $assessment->allow_review,
            'status'               => $assessment->status,
            'visibility'           => $assessment->visibility,
        ]);

        $response->assertForbidden();
    }

    public function test_lms_admins_can_update_any_assessment(): void
    {
        $owner      = User::factory()->create(['role' => 'content_manager']);
        $admin      = User::factory()->create(['role' => 'lms_admin']);
        $course     = Course::factory()->create(['user_id' => $owner->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $owner->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($admin)->put("/courses/{$course->id}/assessments/{$assessment->id}", [
            'title'                => 'Admin Updated Title',
            'description'          => $assessment->description,
            'passing_score'        => $assessment->passing_score,
            'max_attempts'         => $assessment->max_attempts,
            'shuffle_questions'    => $assessment->shuffle_questions,
            'show_correct_answers' => $assessment->show_correct_answers,
            'allow_review'         => $assessment->allow_review,
            'status'               => $assessment->status,
            'visibility'           => $assessment->visibility,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'id'    => $assessment->id,
            'title' => 'Admin Updated Title',
        ]);
    }

    public function test_content_managers_cannot_delete_published_assessments(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
        ]);

        $response = $this->actingAs($user)->delete("/courses/{$course->id}/assessments/{$assessment->id}");

        $response->assertForbidden();
    }

    public function test_lms_admins_can_delete_any_assessment(): void
    {
        $owner      = User::factory()->create(['role' => 'content_manager']);
        $admin      = User::factory()->create(['role' => 'lms_admin']);
        $course     = Course::factory()->create(['user_id' => $owner->id]);
        $assessment = Assessment::factory()->draft()->create([
            'course_id' => $course->id,
            'user_id'   => $owner->id,
        ]);

        $response = $this->actingAs($admin)->delete("/courses/{$course->id}/assessments/{$assessment->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('assessments', ['id' => $assessment->id]);
    }

    public function test_lms_admins_can_publish_assessments(): void
    {
        $owner      = User::factory()->create(['role' => 'content_manager']);
        $admin      = User::factory()->create(['role' => 'lms_admin']);
        $course     = Course::factory()->create(['user_id' => $owner->id]);
        $assessment = Assessment::factory()->draft()->create([
            'course_id' => $course->id,
            'user_id'   => $owner->id,
        ]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/assessments/{$assessment->id}/publish");

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'id'           => $assessment->id,
            'status'       => 'published',
            'published_by' => $admin->id,
        ]);
    }

    public function test_lms_admins_can_unpublish_assessments(): void
    {
        $owner      = User::factory()->create(['role' => 'content_manager']);
        $admin      = User::factory()->create(['role' => 'lms_admin']);
        $course     = Course::factory()->create(['user_id' => $owner->id]);
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id'   => $owner->id,
        ]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/assessments/{$assessment->id}/unpublish");

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'id'     => $assessment->id,
            'status' => 'draft',
        ]);
    }

    public function test_lms_admins_can_archive_assessments(): void
    {
        $owner      = User::factory()->create(['role' => 'content_manager']);
        $admin      = User::factory()->create(['role' => 'lms_admin']);
        $course     = Course::factory()->create(['user_id' => $owner->id]);
        $assessment = Assessment::factory()->draft()->create([
            'course_id' => $course->id,
            'user_id'   => $owner->id,
        ]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/assessments/{$assessment->id}/archive");

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'id'     => $assessment->id,
            'status' => 'archived',
        ]);
    }

    public function test_assessment_can_have_questions(): void
    {
        $user       = User::factory()->create(['role' => 'content_manager']);
        $course     = Course::factory()->create(['user_id' => $user->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $user->id,
        ]);
        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'multiple_choice',
        ]);

        $this->assertCount(1, $assessment->questions);
        $this->assertEquals($question->question_text, $assessment->questions->first()->question_text);
    }

    public function test_assessments_index_filters_by_status(): void
    {
        $user   = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->create(['user_id' => $user->id]);
        Assessment::factory()->draft()->create(['course_id' => $course->id, 'user_id' => $user->id, 'title' => 'Draft Assessment']);
        Assessment::factory()->published()->create(['course_id' => $course->id, 'user_id' => $user->id, 'title' => 'Published Assessment']);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/assessments?status=draft");

        $response->assertOk();
        $response->assertSee('Draft Assessment');
    }

    public function test_assessments_index_filters_by_search(): void
    {
        $user   = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->create(['user_id' => $user->id]);
        Assessment::factory()->create(['course_id' => $course->id, 'user_id' => $user->id, 'title' => 'Math Assessment']);
        Assessment::factory()->create(['course_id' => $course->id, 'user_id' => $user->id, 'title' => 'Science Assessment']);

        $response = $this->actingAs($user)->get("/courses/{$course->id}/assessments?search=Math");

        $response->assertOk();
        $response->assertSee('Math Assessment');
    }
}