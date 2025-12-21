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

class LessonProgressCompletionTest extends TestCase
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

        $this->user    = User::factory()->create(['role' => 'learner']);
        $this->course  = Course::factory()->published()->create();
        $this->section = CourseSection::factory()->create(['course_id' => $this->course->id]);
        $this->lesson  = Lesson::factory()->create([
            'course_section_id' => $this->section->id,
            'content_type'      => 'text',
        ]);
        $this->enrollment = Enrollment::factory()->create([
            'user_id'             => $this->user->id,
            'course_id'           => $this->course->id,
            'status'              => 'active',
            'progress_percentage' => 0,
        ]);
    }

    public function test_lesson_progress_updates_last_lesson_on_enrollment(): void
    {
        $this->assertNull($this->enrollment->last_lesson_id);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 1,
            'total_pages'  => 5,
        ]);

        $response->assertOk();
        $this->enrollment->refresh();
        $this->assertEquals($this->lesson->id, $this->enrollment->last_lesson_id);
    }

    public function test_lesson_progress_with_media_updates_last_lesson(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        $this->assertNull($this->enrollment->last_lesson_id);

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 60,
            'duration_seconds' => 300,
        ]);

        $response->assertOk();
        $this->enrollment->refresh();
        $this->assertEquals($this->lesson->id, $this->enrollment->last_lesson_id);
    }

    public function test_complete_endpoint_updates_last_lesson(): void
    {
        $this->assertNull($this->enrollment->last_lesson_id);

        $response = $this->actingAs($this->user)->postJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/complete");

        $response->assertOk();
        $this->enrollment->refresh();
        $this->assertEquals($this->lesson->id, $this->enrollment->last_lesson_id);
    }

    public function test_lesson_view_returns_correct_progress_data_for_continuation(): void
    {
        // Create progress with specific data
        LessonProgress::create([
            'enrollment_id'          => $this->enrollment->id,
            'lesson_id'              => $this->lesson->id,
            'current_page'           => 3,
            'total_pages'            => 10,
            'highest_page_reached'   => 5,
            'is_completed'           => false,
            'media_position_seconds' => 120,
            'media_duration_seconds' => 600,
        ]);

        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}/lessons/{$this->lesson->id}");

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('lessons/Show')
                ->has('lessonProgress')
                ->where('lessonProgress.current_page', 3)
                ->where('lessonProgress.total_pages', 10)
                ->where('lessonProgress.highest_page_reached', 5)
                ->where('lessonProgress.is_completed', false)
                ->where('lessonProgress.media_position_seconds', 120)
                ->where('lessonProgress.media_duration_seconds', 600)
        );
    }

    public function test_progress_tracking_works_across_multiple_lessons(): void
    {
        $lesson2 = Lesson::factory()->create([
            'course_section_id' => $this->section->id,
            'content_type'      => 'text',
        ]);

        // Progress on first lesson
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 5,
            'total_pages'  => 5, // Complete first lesson
        ]);

        // Progress on second lesson
        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$lesson2->id}/progress", [
            'current_page' => 2,
            'total_pages'  => 8,
        ]);

        $response->assertOk();

        // Check that last lesson was updated to the second lesson
        $this->enrollment->refresh();
        $this->assertEquals($lesson2->id, $this->enrollment->last_lesson_id);

        // Check course progress (1 of 2 lessons completed = 50%)
        $this->assertEquals(50, $this->enrollment->progress_percentage);
    }

    public function test_progress_resumes_correctly_after_page_refresh(): void
    {
        // Create initial progress
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page' => 3,
            'total_pages'  => 7,
        ]);

        // Simulate page refresh by getting the lesson view
        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}/lessons/{$this->lesson->id}");

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('lessons/Show')
                ->has('lessonProgress')
                ->where('lessonProgress.current_page', 3)
                ->where('lessonProgress.total_pages', 7)
        );
    }

    public function test_media_progress_resumes_correctly(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        // Create initial media progress
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", [
            'position_seconds' => 150,
            'duration_seconds' => 420,
        ]);

        // Simulate page refresh
        $response = $this->actingAs($this->user)->get("/courses/{$this->course->id}/lessons/{$this->lesson->id}");

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
                ->component('lessons/Show')
                ->has('lessonProgress')
                ->where('lessonProgress.media_position_seconds', 150)
                ->where('lessonProgress.media_duration_seconds', 420)
        );
    }

    public function test_progress_tracking_with_pagination_metadata(): void
    {
        $metadata = [
            'viewportHeight' => 800,
            'contentHeight'  => 1200,
            'pageBreaks'     => [0, 400, 800, 1200],
        ];

        $response = $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page'        => 2,
            'total_pages'         => 4,
            'pagination_metadata' => $metadata,
        ]);

        $response->assertOk();

        $progress = LessonProgress::where('enrollment_id', $this->enrollment->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        $this->assertEquals($metadata, $progress->pagination_metadata);

        // Verify metadata is returned in response
        $response->assertJsonPath('progress.pagination_metadata', $metadata);
    }

    public function test_time_spent_accumulates_across_multiple_updates(): void
    {
        // First update with some time
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page'       => 1,
            'total_pages'        => 5,
            'time_spent_seconds' => 45,
        ]);

        // Second update with more time
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page'       => 2,
            'total_pages'        => 5,
            'time_spent_seconds' => 30,
        ]);

        // Third update with even more time
        $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", [
            'current_page'       => 3,
            'total_pages'        => 5,
            'time_spent_seconds' => 60,
        ]);

        $progress = LessonProgress::where('enrollment_id', $this->enrollment->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        // 45 + 30 + 60 = 135 seconds total
        $this->assertEquals(135, $progress->time_spent_seconds);
    }

    public function test_progress_tracking_handles_rapid_page_changes(): void
    {
        // Simulate rapid page changes (like scrolling through content)
        $updates = [
            ['current_page' => 1, 'total_pages' => 10],
            ['current_page' => 3, 'total_pages' => 10],
            ['current_page' => 2, 'total_pages' => 10], // Going back
            ['current_page' => 5, 'total_pages' => 10],
            ['current_page' => 4, 'total_pages' => 10], // Going back again
            ['current_page' => 7, 'total_pages' => 10],
        ];

        foreach ($updates as $update) {
            $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress", $update);
        }

        $progress = LessonProgress::where('enrollment_id', $this->enrollment->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        // Current page should be the last one (7)
        $this->assertEquals(7, $progress->current_page);
        // Highest page reached should be 7 (not affected by going back)
        $this->assertEquals(7, $progress->highest_page_reached);
    }

    public function test_media_progress_with_frequent_time_updates(): void
    {
        $this->lesson->update(['content_type' => 'video']);

        // Simulate frequent media time updates (like during video playback)
        $timeUpdates = [
            ['position_seconds' => 10, 'duration_seconds' => 600],
            ['position_seconds' => 30, 'duration_seconds' => 600],
            ['position_seconds' => 75, 'duration_seconds' => 600],
            ['position_seconds' => 150, 'duration_seconds' => 600],
            ['position_seconds' => 240, 'duration_seconds' => 600],
            ['position_seconds' => 360, 'duration_seconds' => 600],
        ];

        foreach ($timeUpdates as $update) {
            $this->actingAs($this->user)->patchJson("/courses/{$this->course->id}/lessons/{$this->lesson->id}/progress/media", $update);
        }

        $progress = LessonProgress::where('enrollment_id', $this->enrollment->id)
            ->where('lesson_id', $this->lesson->id)
            ->first();

        // Should have the latest position
        $this->assertEquals(360, $progress->media_position_seconds);
        $this->assertEquals(600, $progress->media_duration_seconds);
        // Should be 60% progress
        $this->assertEquals(60.00, $progress->media_progress_percentage);
    }
}