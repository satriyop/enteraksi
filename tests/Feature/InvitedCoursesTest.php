<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitedCoursesTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_can_see_pending_invitations_on_dashboard(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
            'message' => 'Please join this course.',
        ]);

        $response = $this->actingAs($learner)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('invitedCourses', 1)
                ->where('invitedCourses.0.title', $course->title)
                ->where('invitedCourses.0.message', 'Please join this course.')
        );
    }

    public function test_learner_can_accept_invitation(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        $invitation = CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($learner)->post("/invitations/{$invitation->id}/accept");

        $response->assertRedirect();
        $this->assertDatabaseHas('course_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_learner_can_decline_invitation(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        $invitation = CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($learner)->post("/invitations/{$invitation->id}/decline");

        $response->assertRedirect();
        $this->assertDatabaseHas('course_invitations', [
            'id' => $invitation->id,
            'status' => 'declined',
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $learner->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_learner_cannot_accept_others_invitation(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $otherLearner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        $invitation = CourseInvitation::factory()->pending()->create([
            'user_id' => $otherLearner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($learner)->post("/invitations/{$invitation->id}/accept");

        $response->assertForbidden();
    }

    public function test_expired_invitations_are_not_shown(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        CourseInvitation::factory()->expired()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($learner)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('invitedCourses', 0));
    }

    public function test_already_accepted_invitations_are_not_shown(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();

        CourseInvitation::factory()->accepted()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($learner)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('invitedCourses', 0));
    }

    public function test_invitation_includes_lessons_count(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()
            ->published()
            ->has(
                \App\Models\CourseSection::factory()
                    ->has(\App\Models\Lesson::factory()->count(5), 'lessons'),
                'sections'
            )
            ->create();

        CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
        ]);

        $response = $this->actingAs($learner)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->where('invitedCourses.0.lessons_count', 5)
        );
    }

    public function test_invitation_includes_expires_at(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'lms_admin']);
        $course = Course::factory()->published()->create();
        $expiresAt = now()->addDays(7);

        CourseInvitation::factory()->pending()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'invited_by' => $admin->id,
            'expires_at' => $expiresAt,
        ]);

        $response = $this->actingAs($learner)->get('/learner/dashboard');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->has('invitedCourses.0.expires_at')
        );
    }
}
