<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Course $course;

    private CourseSection $section;

    private Lesson $lesson;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'learner']);
        $this->course = Course::factory()->published()->create();
        $this->section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        $this->lesson = Lesson::factory()->create([
            'course_section_id' => $this->section->id,
            'content_type' => 'text',
        ]);
        $this->enrollment = Enrollment::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'progress_percentage' => 0,
        ]);
    }

    public function test_guest_cannot_update_progress(): void
    {
        $response = $this->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 2,
            'total_pages' => 10,
        ]);

        $response->assertUnauthorized();
    }

    public function test_non_enrolled_user_cannot_update_progress(): void
    {
        $otherUser = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($otherUser)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 2,
            'total_pages' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_enrolled_user_can_update_progress(): void
    {
        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 3,
            'total_pages' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'progress' => [
                'current_page',
                'total_pages',
                'highest_page_reached',
                'is_completed',
                'progress_percentage',
            ],
            'enrollment' => [
                'progress_percentage',
            ],
        ]);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'current_page' => 3,
            'total_pages' => 10,
            'highest_page_reached' => 3,
            'is_completed' => false,
        ]);
    }

    public function test_progress_creates_record_on_first_update(): void
    {
        $this->assertDatabaseMissing('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 1,
            'total_pages' => 5,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
        ]);
    }

    public function test_highest_page_reached_only_increases(): void
    {
        // First, go to page 5
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 5,
            'total_pages' => 10,
        ]);

        // Then go back to page 2
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 2,
            'total_pages' => 10,
        ]);

        $progress = LessonProgress::where('enrollment_id', $this->enrollment->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        $this->assertEquals(2, $progress->current_page);
        $this->assertEquals(5, $progress->highest_page_reached); // Should remain 5
    }

    public function test_lesson_completes_when_reaching_last_page(): void
    {
        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 10,
            'total_pages' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('progress.is_completed', true);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_course_progress_updates_on_lesson_completion(): void
    {
        // Create another lesson so we have 2 total
        $lesson2 = Lesson::factory()->create([
            'course_section_id' => $this->section->id,
            'content_type' => 'text',
        ]);

        // Complete the first lesson
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 5,
            'total_pages' => 5,
        ]);

        $this->enrollment->refresh();

        // 1 of 2 lessons completed = 50%
        $this->assertEquals(50, $this->enrollment->progress_percentage);
    }

    public function test_enrollment_completes_when_all_lessons_done(): void
    {
        // Complete the only lesson
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 5,
            'total_pages' => 5,
        ]);

        $this->enrollment->refresh();

        $this->assertEquals(100, $this->enrollment->progress_percentage);
        $this->assertEquals('completed', $this->enrollment->status);
        $this->assertNotNull($this->enrollment->completed_at);
    }

    public function test_progress_validates_page_numbers(): void
    {
        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 0,
            'total_pages' => 10,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('current_page');
    }

    public function test_progress_validates_total_pages(): void
    {
        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 5,
            'total_pages' => 0,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('total_pages');
    }

    public function test_current_page_capped_at_total_pages(): void
    {
        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 15,
            'total_pages' => 10,
        ]);

        $response->assertOk();

        // Page should be capped at 10
        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'current_page' => 10,
        ]);
    }

    public function test_pagination_metadata_is_stored(): void
    {
        $metadata = [
            'viewportHeight' => 800,
            'contentHeight' => 560,
            'pageBreaks' => [0, 5, 10],
        ];

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 1,
            'total_pages' => 3,
            'pagination_metadata' => $metadata,
        ]);

        $response->assertOk();

        $progress = LessonProgress::where('enrollment_id', $this->enrollment->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        $this->assertEquals($metadata, $progress->pagination_metadata);
    }

    public function test_time_spent_accumulates(): void
    {
        // First update with time
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 1,
            'total_pages' => 5,
            'time_spent_seconds' => 30,
        ]);

        // Second update with more time
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 2,
            'total_pages' => 5,
            'time_spent_seconds' => 45,
        ]);

        $progress = LessonProgress::where('enrollment_id', $this->enrollment->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        $this->assertEquals(75, $progress->time_spent_seconds);
    }

    public function test_dropped_enrollment_cannot_update_progress(): void
    {
        $this->enrollment->update(['status' => 'dropped']);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 2,
            'total_pages' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_lesson_must_belong_to_course(): void
    {
        $otherCourse = Course::factory()->published()->create();

        // User is enrolled in other course, but lesson belongs to original course
        Enrollment::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $otherCourse->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$otherCourse->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 2,
            'total_pages' => 10,
        ]);

        $response->assertNotFound();
    }

    public function test_complete_endpoint_marks_lesson_completed(): void
    {
        // First create a progress record
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 3,
            'total_pages' => 10,
        ]);

        // Then mark as completed
        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('progress.is_completed', true);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_lesson_view_includes_progress_for_paginated_content(): void
    {
        // Create progress first
        LessonProgress::create([
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'current_page' => 3,
            'total_pages' => 10,
            'highest_page_reached' => 5,
            'is_completed' => false,
        ]);

        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}/lessons/{$this->lesson->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('lessons/Show')
            ->has('lessonProgress')
            ->where('lessonProgress.current_page', 3)
            ->where('lessonProgress.total_pages', 10)
        );
    }

    public function test_lesson_view_includes_progress_for_video_content(): void
    {
        // Update lesson to be video type
        $this->lesson->update(['content_type' => 'video']);

        // Create progress with media tracking
        LessonProgress::create([
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'current_page' => 1,
            'media_position_seconds' => 120,
            'media_duration_seconds' => 600,
            'media_progress_percentage' => 20.00,
            'is_completed' => false,
        ]);

        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}/lessons/{$this->lesson->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('lessons/Show')
            ->has('lessonProgress')
            ->where('lessonProgress.media_position_seconds', 120)
        );
    }

    // ========== Media Progress Tests ==========

    public function test_enrolled_user_can_update_media_progress(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 60,
            'duration_seconds' => 300,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'progress' => [
                'media_position_seconds',
                'media_duration_seconds',
                'media_progress_percentage',
                'is_completed',
            ],
            'enrollment' => [
                'progress_percentage',
            ],
        ]);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'media_position_seconds' => 60,
            'media_duration_seconds' => 300,
            'media_progress_percentage' => 20.00,
        ]);
    }

    public function test_media_progress_auto_completes_at_90_percent(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 270, // 90% of 300
            'duration_seconds' => 300,
        ]);

        $response->assertOk();
        $response->assertJsonPath('progress.is_completed', true);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_media_progress_does_not_complete_below_90_percent(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 250, // ~83% of 300
            'duration_seconds' => 300,
        ]);

        $response->assertOk();
        $response->assertJsonPath('progress.is_completed', false);
    }

    public function test_media_progress_validates_position_seconds(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => -1,
            'duration_seconds' => 300,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('position_seconds');
    }

    public function test_media_progress_validates_duration_seconds(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 60,
            'duration_seconds' => 0,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_seconds');
    }

    public function test_media_progress_caps_position_at_duration(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 400, // Exceeds duration
            'duration_seconds' => 300,
        ]);

        $response->assertOk();

        // Position should be capped at duration
        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $this->enrollment->id,
            'lesson_id' => $this->lesson->id,
            'media_position_seconds' => 300,
        ]);
    }

    public function test_non_enrolled_user_cannot_update_media_progress(): void
    {
        $this->lesson->update(['content_type' => 'video']);
        $otherUser = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($otherUser)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 60,
            'duration_seconds' => 300,
        ]);

        $response->assertForbidden();
    }

    public function test_guest_cannot_update_media_progress(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $response = $this->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 60,
            'duration_seconds' => 300,
        ]);

        $response->assertUnauthorized();
    }

    public function test_media_progress_updates_course_progress_on_completion(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        // Complete the video (90%+)
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 270,
            'duration_seconds' => 300,
        ]);

        $this->enrollment->refresh();

        // Only 1 lesson in course, so should be 100%
        $this->assertEquals(100, $this->enrollment->progress_percentage);
        $this->assertEquals('completed', $this->enrollment->status);
    }

    public function test_updates_last_lesson_id_on_enrollment(): void
    {
        $this->assertNull($this->enrollment->last_lesson_id);

        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 1,
            'total_pages' => 5,
        ]);

        $this->enrollment->refresh();

        $this->assertEquals($this->lesson->id, $this->enrollment->last_lesson_id);
    }
}
