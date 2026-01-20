<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests the course publishing state machine.
 *
 * State Machine: draft → published ↔ archived
 *
 * Test Perspectives:
 * - LMS Admin: Full control over publishing state
 * - Content Manager: Can create but not publish
 * - Learner: Can only see/enroll in published courses
 * - Data Integrity: published_at, published_by tracking
 * - Business Rules: Editability, enrollment constraints
 */
class CoursePublishingStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private User $lmsAdmin;

    private User $contentManager;

    private User $learner;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);
        $this->learner = User::factory()->create(['role' => 'learner']);
        $this->category = Category::factory()->create();
    }

    private function createDraftCourse(?User $owner = null): Course
    {
        return Course::factory()->create([
            'user_id' => $owner?->id ?? $this->contentManager->id,
            'status' => 'draft',
            'visibility' => 'public',
            'category_id' => $this->category->id,
        ]);
    }

    private function createPublishedCourse(?User $owner = null): Course
    {
        return Course::factory()->published()->create([
            'user_id' => $owner?->id ?? $this->contentManager->id,
            'visibility' => 'public',
            'category_id' => $this->category->id,
        ]);
    }

    private function createArchivedCourse(?User $owner = null): Course
    {
        return Course::factory()->create([
            'user_id' => $owner?->id ?? $this->contentManager->id,
            'status' => 'archived',
            'visibility' => 'public',
            'category_id' => $this->category->id,
        ]);
    }

    // ========== PUBLISH TRANSITIONS (draft → published) ==========

    public function test_lms_admin_can_publish_draft_course(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $course->refresh();
        $this->assertEquals('published', $course->status);
        $this->assertNotNull($course->published_at);
        $this->assertEquals($this->lmsAdmin->id, $course->published_by);
    }

    public function test_publish_sets_published_at_timestamp(): void
    {
        $course = $this->createDraftCourse();
        $beforePublish = now()->subSecond();

        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");

        $course->refresh();
        $this->assertTrue($course->published_at->gte($beforePublish));
    }

    public function test_publish_sets_published_by_to_current_user(): void
    {
        $course = $this->createDraftCourse();

        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");

        $course->refresh();
        $this->assertEquals($this->lmsAdmin->id, $course->published_by);
    }

    public function test_content_manager_cannot_publish_own_course(): void
    {
        $course = $this->createDraftCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->post("/courses/{$course->id}/publish");

        $response->assertForbidden();

        $course->refresh();
        $this->assertEquals('draft', $course->status);
    }

    public function test_learner_cannot_publish_course(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$course->id}/publish");

        $response->assertForbidden();
    }

    public function test_guest_cannot_publish_course(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->post("/courses/{$course->id}/publish");

        $response->assertRedirect(route('login'));
    }

    // ========== UNPUBLISH TRANSITIONS (published → draft) ==========

    public function test_lms_admin_can_unpublish_published_course(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/unpublish");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $course->refresh();
        $this->assertEquals('draft', $course->status);
    }

    public function test_unpublish_clears_published_at(): void
    {
        $course = $this->createPublishedCourse();
        $this->assertNotNull($course->published_at);

        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/unpublish");

        $course->refresh();
        $this->assertNull($course->published_at);
    }

    public function test_unpublish_clears_published_by(): void
    {
        $course = $this->createPublishedCourse();
        $this->assertNotNull($course->published_by);

        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/unpublish");

        $course->refresh();
        $this->assertNull($course->published_by);
    }

    public function test_content_manager_cannot_unpublish_course(): void
    {
        $course = $this->createPublishedCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->post("/courses/{$course->id}/unpublish");

        $response->assertForbidden();

        $course->refresh();
        $this->assertEquals('published', $course->status);
    }

    public function test_learner_cannot_unpublish_course(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$course->id}/unpublish");

        $response->assertForbidden();
    }

    // ========== ARCHIVE TRANSITIONS (published → archived) ==========

    public function test_lms_admin_can_archive_published_course(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/archive");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $course->refresh();
        $this->assertEquals('archived', $course->status);
    }

    public function test_lms_admin_can_archive_draft_course(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/archive");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $course->refresh();
        $this->assertEquals('archived', $course->status);
    }

    public function test_content_manager_cannot_archive_course(): void
    {
        $course = $this->createPublishedCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->post("/courses/{$course->id}/archive");

        $response->assertForbidden();
    }

    // ========== SET STATUS (direct status changes by LMS Admin) ==========

    public function test_lms_admin_can_set_status_to_published(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/status", [
                'status' => 'published',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('published', $course->status);
        $this->assertNotNull($course->published_at);
    }

    public function test_lms_admin_can_set_status_to_draft(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/status", [
                'status' => 'draft',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('draft', $course->status);
        $this->assertNull($course->published_at);
    }

    public function test_lms_admin_can_set_status_to_archived(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/status", [
                'status' => 'archived',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('archived', $course->status);
    }

    public function test_content_manager_cannot_set_status(): void
    {
        $course = $this->createDraftCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->patch("/courses/{$course->id}/status", [
                'status' => 'published',
            ]);

        $response->assertForbidden();
    }

    public function test_set_status_validates_allowed_values(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/status", [
                'status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors('status');
    }

    // ========== VISIBILITY CHANGES ==========

    public function test_lms_admin_can_set_visibility_to_public(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'restricted',
        ]);

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/visibility", [
                'visibility' => 'public',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('public', $course->visibility);
    }

    public function test_lms_admin_can_set_visibility_to_restricted(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/visibility", [
                'visibility' => 'restricted',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('restricted', $course->visibility);
    }

    public function test_lms_admin_can_set_visibility_to_hidden(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/visibility", [
                'visibility' => 'hidden',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('hidden', $course->visibility);
    }

    public function test_content_manager_cannot_set_visibility(): void
    {
        $course = $this->createDraftCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->patch("/courses/{$course->id}/visibility", [
                'visibility' => 'restricted',
            ]);

        $response->assertForbidden();
    }

    public function test_set_visibility_validates_allowed_values(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/visibility", [
                'visibility' => 'invalid_visibility',
            ]);

        $response->assertSessionHasErrors('visibility');
    }

    // ========== EDITABILITY BASED ON STATUS ==========

    public function test_content_manager_can_edit_own_draft_course(): void
    {
        $course = $this->createDraftCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->put("/courses/{$course->id}", [
                'title' => 'Updated Title',
                'short_description' => 'Updated description',
                'visibility' => 'public',
                'difficulty_level' => 'beginner',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('Updated Title', $course->title);
    }

    public function test_content_manager_cannot_edit_published_course(): void
    {
        $course = $this->createPublishedCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->put("/courses/{$course->id}", [
                'title' => 'Updated Title',
                'short_description' => 'Updated description',
                'visibility' => 'public',
            ]);

        $response->assertForbidden();
    }

    public function test_lms_admin_can_edit_published_course(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->put("/courses/{$course->id}", [
                'title' => 'Admin Updated Title',
                'short_description' => 'Admin updated description',
                'visibility' => 'public',
                'difficulty_level' => 'intermediate',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('Admin Updated Title', $course->title);
    }

    public function test_is_editable_attribute_for_draft_course(): void
    {
        $course = $this->createDraftCourse();

        $this->assertTrue($course->is_editable);
    }

    public function test_is_editable_attribute_for_published_course(): void
    {
        $course = $this->createPublishedCourse();

        $this->assertFalse($course->is_editable);
    }

    public function test_is_editable_attribute_for_archived_course(): void
    {
        $course = $this->createArchivedCourse();

        // Archived courses are editable according to current implementation
        $this->assertTrue($course->is_editable);
    }

    // ========== ENROLLMENT IMPLICATIONS ==========

    public function test_cannot_enroll_in_draft_course(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$course->id}/enroll");

        $response->assertForbidden();
    }

    public function test_cannot_enroll_in_archived_course(): void
    {
        $course = $this->createArchivedCourse();

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$course->id}/enroll");

        $response->assertForbidden();
    }

    public function test_can_enroll_in_published_public_course(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$course->id}/enroll");

        $response->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_existing_enrollments_persist_after_unpublish(): void
    {
        $course = $this->createPublishedCourse();

        // Enroll a learner
        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        // Unpublish the course
        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/unpublish");

        // Enrollment should still exist
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_enrolled_learner_can_view_unpublished_course(): void
    {
        $course = $this->createPublishedCourse();

        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);

        // Unpublish
        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/unpublish");

        $course->refresh();
        $this->assertEquals('draft', $course->status);

        // Enrolled learner can still view
        $response = $this->actingAs($this->learner)
            ->get("/courses/{$course->id}");

        $response->assertOk();
    }

    public function test_new_learners_cannot_enroll_after_unpublish(): void
    {
        $course = $this->createPublishedCourse();

        // Unpublish
        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/unpublish");

        // New learner cannot enroll
        $newLearner = User::factory()->create(['role' => 'learner']);
        $response = $this->actingAs($newLearner)
            ->post("/courses/{$course->id}/enroll");

        $response->assertForbidden();
    }

    // ========== DELETE CONSTRAINTS BASED ON STATUS ==========

    public function test_content_manager_can_delete_own_draft_course(): void
    {
        $course = $this->createDraftCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->delete("/courses/{$course->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_content_manager_cannot_delete_own_published_course(): void
    {
        $course = $this->createPublishedCourse($this->contentManager);

        $response = $this->actingAs($this->contentManager)
            ->delete("/courses/{$course->id}");

        $response->assertForbidden();
    }

    public function test_lms_admin_can_delete_any_course(): void
    {
        $publishedCourse = $this->createPublishedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->delete("/courses/{$publishedCourse->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('courses', ['id' => $publishedCourse->id]);
    }

    // ========== MODEL SCOPES ==========

    public function test_published_scope(): void
    {
        $draft = $this->createDraftCourse();
        $published = $this->createPublishedCourse();
        $archived = $this->createArchivedCourse();

        $publishedCourses = Course::published()->get();

        $this->assertCount(1, $publishedCourses);
        $this->assertEquals($published->id, $publishedCourses->first()->id);
    }

    public function test_draft_scope(): void
    {
        $draft = $this->createDraftCourse();
        $published = $this->createPublishedCourse();

        $draftCourses = Course::draft()->get();

        $this->assertCount(1, $draftCourses);
        $this->assertEquals($draft->id, $draftCourses->first()->id);
    }

    public function test_archived_scope(): void
    {
        $draft = $this->createDraftCourse();
        $published = $this->createPublishedCourse();
        $archived = $this->createArchivedCourse();

        $archivedCourses = Course::archived()->get();

        $this->assertCount(1, $archivedCourses);
        $this->assertEquals($archived->id, $archivedCourses->first()->id);
    }

    public function test_visible_scope(): void
    {
        $public = Course::factory()->create(['visibility' => 'public']);
        $restricted = Course::factory()->create(['visibility' => 'restricted']);
        $hidden = Course::factory()->create(['visibility' => 'hidden']);

        $visibleCourses = Course::visible()->get();

        $this->assertCount(1, $visibleCourses);
        $this->assertEquals($public->id, $visibleCourses->first()->id);
    }

    // ========== RELATIONSHIPS ==========

    public function test_published_by_relationship(): void
    {
        $course = $this->createDraftCourse();

        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");

        $course->refresh();

        $this->assertNotNull($course->publishedBy);
        $this->assertEquals($this->lmsAdmin->id, $course->publishedBy->id);
    }

    public function test_published_by_is_null_for_draft_course(): void
    {
        $course = $this->createDraftCourse();

        $this->assertNull($course->published_by);
        $this->assertNull($course->publishedBy);
    }

    // ========== EDGE CASES ==========

    public function test_publish_already_published_course(): void
    {
        $course = $this->createPublishedCourse();
        $originalPublishedAt = $course->published_at;

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('published', $course->status);
        // Published_at may be updated (depends on implementation)
    }

    public function test_unpublish_already_draft_course(): void
    {
        $course = $this->createDraftCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/unpublish");

        // Should succeed but be essentially a no-op
        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('draft', $course->status);
        $this->assertNull($course->published_at);
    }

    public function test_archive_already_archived_course(): void
    {
        $course = $this->createArchivedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/archive");

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('archived', $course->status);
    }

    public function test_can_republish_archived_course(): void
    {
        $course = $this->createArchivedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('published', $course->status);
        $this->assertNotNull($course->published_at);
    }

    public function test_can_revert_archived_to_draft(): void
    {
        $course = $this->createArchivedCourse();

        $response = $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/status", [
                'status' => 'draft',
            ]);

        $response->assertRedirect();

        $course->refresh();
        $this->assertEquals('draft', $course->status);
    }

    // ========== CONCURRENT MODIFICATION SCENARIOS ==========

    public function test_multiple_status_changes_in_sequence(): void
    {
        $course = $this->createDraftCourse();

        // Publish
        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");
        $course->refresh();
        $this->assertEquals('published', $course->status);

        // Archive
        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/archive");
        $course->refresh();
        $this->assertEquals('archived', $course->status);

        // Back to draft
        $this->actingAs($this->lmsAdmin)
            ->patch("/courses/{$course->id}/status", ['status' => 'draft']);
        $course->refresh();
        $this->assertEquals('draft', $course->status);

        // Publish again
        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");
        $course->refresh();
        $this->assertEquals('published', $course->status);
    }

    public function test_status_transitions_preserve_other_fields(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'title' => 'Original Title',
            'short_description' => 'Original Description',
            'visibility' => 'restricted',
            'status' => 'draft',
            'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->lmsAdmin)
            ->post("/courses/{$course->id}/publish");

        $course->refresh();

        $this->assertEquals('Original Title', $course->title);
        $this->assertEquals('Original Description', $course->short_description);
        $this->assertEquals('restricted', $course->visibility);
        $this->assertEquals($this->category->id, $course->category_id);
    }
}
