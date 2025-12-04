<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CourseInvitationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_manager_can_invite_learner_to_own_course(): void
    {
        $contentManager = User::factory()->create(['role' => 'content_manager']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create(['user_id' => $contentManager->id]);

        $response = $this->actingAs($contentManager)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner->id,
            'message' => 'Please join this course.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('course_invitations', [
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $contentManager->id,
            'status' => 'pending',
            'message' => 'Please join this course.',
        ]);
    }

    public function test_lms_admin_can_invite_learner_to_any_course(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $contentManager = User::factory()->create(['role' => 'content_manager']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create(['user_id' => $contentManager->id]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('course_invitations', [
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);
    }

    public function test_trainer_can_invite_learner_to_any_course(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer']);
        $contentManager = User::factory()->create(['role' => 'content_manager']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create(['user_id' => $contentManager->id]);

        $response = $this->actingAs($trainer)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('course_invitations', [
            'user_id' => $learner->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_content_manager_cannot_invite_to_others_course(): void
    {
        $contentManager1 = User::factory()->create(['role' => 'content_manager']);
        $contentManager2 = User::factory()->create(['role' => 'content_manager']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create(['user_id' => $contentManager2->id]);

        $response = $this->actingAs($contentManager1)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner->id,
        ]);

        $response->assertForbidden();
    }

    public function test_learner_cannot_invite_others(): void
    {
        $learner1 = User::factory()->create(['role' => 'learner']);
        $learner2 = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();

        $response = $this->actingAs($learner1)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner2->id,
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_invite_non_learner_user(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $contentManager = User::factory()->create(['role' => 'content_manager']);
        $course = Course::factory()->published()->create();

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/invitations", [
            'user_id' => $contentManager->id,
        ]);

        $response->assertSessionHasErrors('user_id');
    }

    public function test_cannot_invite_already_enrolled_user(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();

        Enrollment::factory()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner->id,
        ]);

        $response->assertSessionHasErrors('user_id');
    }

    public function test_cannot_send_duplicate_pending_invitation(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();

        CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner->id,
        ]);

        $response->assertSessionHasErrors('user_id');
    }

    public function test_admin_can_cancel_pending_invitation(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();

        $invitation = CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete("/courses/{$course->id}/invitations/{$invitation->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('course_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_inviter_can_cancel_own_invitation(): void
    {
        $contentManager = User::factory()->create(['role' => 'content_manager']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create(['user_id' => $contentManager->id]);

        $invitation = CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $contentManager->id,
        ]);

        $response = $this->actingAs($contentManager)->delete("/courses/{$course->id}/invitations/{$invitation->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('course_invitations', ['id' => $invitation->id]);
    }

    public function test_cannot_cancel_accepted_invitation(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();

        $invitation = CourseInvitation::factory()->accepted()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete("/courses/{$course->id}/invitations/{$invitation->id}");

        $response->assertForbidden();
    }

    public function test_search_learners_returns_matching_users(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner1 = User::factory()->create(['role' => 'learner', 'name' => 'Ahmad Wijaya', 'email' => 'ahmad@example.com']);
        $learner2 = User::factory()->create(['role' => 'learner', 'name' => 'Siti Rahayu', 'email' => 'siti@example.com']);
        User::factory()->create(['role' => 'content_manager', 'name' => 'Ahmad Manager']);

        $response = $this->actingAs($admin)->get('/api/users/search?q=ahmad');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Ahmad Wijaya']);
    }

    public function test_search_learners_excludes_enrolled_and_invited_users(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        $enrolledLearner = User::factory()->create(['role' => 'learner', 'name' => 'Enrolled User']);
        $invitedLearner = User::factory()->create(['role' => 'learner', 'name' => 'Invited User']);
        $availableLearner = User::factory()->create(['role' => 'learner', 'name' => 'Available User']);

        Enrollment::factory()->create([
            'user_id' => $enrolledLearner->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        CourseInvitation::factory()->pending()->create([
            'user_id' => $invitedLearner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/api/users/search?q=User&course_id={$course->id}");

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Available User']);
    }

    public function test_bulk_import_invitations_from_csv(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        $learner1 = User::factory()->create(['role' => 'learner', 'email' => 'learner1@example.com']);
        $learner2 = User::factory()->create(['role' => 'learner', 'email' => 'learner2@example.com']);

        $csvContent = "email\nlearner1@example.com\nlearner2@example.com";
        $file = UploadedFile::fake()->createWithContent('invitations.csv', $csvContent);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/invitations/bulk", [
            'file' => $file,
            'message' => 'Welcome to the course!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('course_invitations', [
            'user_id' => $learner1->id,
            'course_id' => $course->id,
            'message' => 'Welcome to the course!',
        ]);
        $this->assertDatabaseHas('course_invitations', [
            'user_id' => $learner2->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_bulk_import_skips_invalid_emails(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        $validLearner = User::factory()->create(['role' => 'learner', 'email' => 'valid@example.com']);

        $csvContent = "email\nvalid@example.com\nnonexistent@example.com\ninvalid@example.com";
        $file = UploadedFile::fake()->createWithContent('invitations.csv', $csvContent);

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/invitations/bulk", [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseHas('course_invitations', [
            'user_id' => $validLearner->id,
            'course_id' => $course->id,
        ]);
        $this->assertEquals(1, CourseInvitation::where('course_id', $course->id)->count());
    }

    public function test_course_show_includes_invitations_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();

        CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/courses/{$course->id}");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('invitations', 1)
                ->where('can.invite', true)
        );
    }

    public function test_invitation_with_expiration_date(): void
    {
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->published()->create();
        $expiresAt = now()->addWeek()->format('Y-m-d');

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/invitations", [
            'user_id' => $learner->id,
            'expires_at' => $expiresAt,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('course_invitations', [
            'user_id' => $learner->id,
            'course_id' => $course->id,
        ]);

        $invitation = CourseInvitation::where('user_id', $learner->id)->first();
        $this->assertNotNull($invitation->expires_at);
    }
}
