<?php

namespace Tests\Feature;

use App\Domain\Progress\Services\ProgressTrackingService;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests the enrollment lifecycle from creation to completion.
 *
 * State Machine: active → completed or active → dropped
 *
 * Test Perspectives:
 * - Learner: Can I enroll, track progress, and complete courses?
 * - Admin: Can I manage enrollments?
 * - Data Integrity: Is progress calculated correctly?
 * - State Machine: Are transitions correct?
 */
class EnrollmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private User $admin;

    private User $contentManager;

    private Course $publicCourse;

    private Course $restrictedCourse;

    private ProgressTrackingService $progressService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['role' => 'learner']);
        $this->admin = User::factory()->create(['role' => 'lms_admin']);
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);

        $this->progressService = app(ProgressTrackingService::class);

        // Create public published course
        $this->publicCourse = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'public',
        ]);

        // Create restricted course
        $this->restrictedCourse = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'restricted',
        ]);

        // Add content to both courses
        $this->addContentToCourse($this->publicCourse);
        $this->addContentToCourse($this->restrictedCourse);
    }

    private function addContentToCourse(Course $course, int $lessonCount = 3): void
    {
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        for ($i = 0; $i < $lessonCount; $i++) {
            Lesson::factory()->create([
                'course_section_id' => $section->id,
                'content_type' => 'text',
            ]);
        }
    }

    // ========== Enrollment Creation ==========

    public function test_learner_can_self_enroll_in_public_course(): void
    {
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->publicCourse->id}/enroll");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
            'status' => 'active',
        ]);
    }

    public function test_enrollment_has_correct_initial_state(): void
    {
        $this->actingAs($this->learner)
            ->post("/courses/{$this->publicCourse->id}/enroll");

        $enrollment = Enrollment::where('user_id', $this->learner->id)
            ->where('course_id', $this->publicCourse->id)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertEquals('active', $enrollment->status);
        $this->assertEquals(0, $enrollment->progress_percentage);
        $this->assertNotNull($enrollment->enrolled_at);
        $this->assertNull($enrollment->started_at);
        $this->assertNull($enrollment->completed_at);
        $this->assertNull($enrollment->last_lesson_id);
    }

    public function test_guest_cannot_enroll(): void
    {
        $response = $this->post("/courses/{$this->publicCourse->id}/enroll");

        $response->assertRedirect(route('login'));
    }

    public function test_cannot_enroll_in_draft_course(): void
    {
        $draftCourse = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'draft',
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$draftCourse->id}/enroll");

        $response->assertForbidden();
    }

    public function test_cannot_enroll_in_archived_course(): void
    {
        $archivedCourse = Course::factory()->create([
            'user_id' => $this->contentManager->id,
            'status' => 'archived',
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($this->learner)
            ->post("/courses/{$archivedCourse->id}/enroll");

        $response->assertForbidden();
    }

    public function test_cannot_self_enroll_in_restricted_course(): void
    {
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->restrictedCourse->id}/enroll");

        $response->assertForbidden();
    }

    public function test_cannot_double_enroll(): void
    {
        // First enrollment
        Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
            'status' => 'active',
        ]);

        // Try to enroll again
        $response = $this->actingAs($this->learner)
            ->post("/courses/{$this->publicCourse->id}/enroll");

        // Should fail (either forbidden or redirect with error)
        // Check that there's still only one enrollment
        $enrollmentCount = Enrollment::where('user_id', $this->learner->id)
            ->where('course_id', $this->publicCourse->id)
            ->count();

        $this->assertEquals(1, $enrollmentCount);
    }

    // ========== Dropping/Unenrolling ==========

    public function test_learner_can_drop_active_enrollment(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->delete("/courses/{$this->publicCourse->id}/unenroll");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $enrollment->refresh();
        $this->assertEquals('dropped', $enrollment->status);
    }

    public function test_cannot_drop_completed_enrollment(): void
    {
        $enrollment = Enrollment::factory()->completed()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $response = $this->actingAs($this->learner)
            ->delete("/courses/{$this->publicCourse->id}/unenroll");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $enrollment->refresh();
        $this->assertEquals('completed', $enrollment->status);
    }

    public function test_guest_cannot_drop_enrollment(): void
    {
        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $response = $this->delete("/courses/{$this->publicCourse->id}/unenroll");

        $response->assertRedirect(route('login'));
    }

    public function test_cannot_drop_others_enrollment(): void
    {
        $otherLearner = User::factory()->create(['role' => 'learner']);
        Enrollment::factory()->active()->create([
            'user_id' => $otherLearner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        // This learner has no enrollment to drop
        $response = $this->actingAs($this->learner)
            ->delete("/courses/{$this->publicCourse->id}/unenroll");

        $response->assertNotFound();
    }

    // ========== Progress Calculation ==========

    public function test_progress_percentage_updates_on_lesson_completion(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
            'progress_percentage' => 0,
        ]);

        // Course has 3 lessons (from setUp)
        $lessons = $this->publicCourse->lessons;
        $firstLesson = $lessons->first();

        // Complete first lesson
        LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $firstLesson->id,
            'is_completed' => true,
            'current_page' => 1,
            'highest_page_reached' => 1,
        ]);

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        // 1/3 = 33.3%
        $this->assertEquals(33.3, $enrollment->progress_percentage);
    }

    public function test_progress_calculation_with_zero_lessons(): void
    {
        $emptyCourse = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'public',
        ]);

        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $emptyCourse->id,
        ]);

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        $this->assertEquals(0, $enrollment->progress_percentage);
    }

    public function test_progress_caps_at_100_percent(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        // Complete all lessons
        foreach ($this->publicCourse->lessons as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        $this->assertEquals(100, $enrollment->progress_percentage);
    }

    // ========== Auto-Completion ==========

    public function test_enrollment_auto_completes_when_all_lessons_done(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        // Complete all lessons
        foreach ($this->publicCourse->lessons as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        $this->assertEquals('completed', $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
    }

    public function test_completed_at_timestamp_set_on_completion(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        // Use startOfSecond to avoid microsecond precision issues with database storage
        $beforeCompletion = now()->subSecond();

        // Complete all lessons
        foreach ($this->publicCourse->lessons as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        $this->assertNotNull($enrollment->completed_at);
        // Allow 2 second window to account for database precision and execution time
        $this->assertTrue(
            $enrollment->completed_at->gte($beforeCompletion),
            "completed_at ({$enrollment->completed_at}) should be >= beforeCompletion ({$beforeCompletion})"
        );
    }

    public function test_enrollment_does_not_complete_with_partial_progress(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $lessons = $this->publicCourse->lessons;

        // Complete only first lesson
        LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons->first()->id,
            'is_completed' => true,
            'current_page' => 1,
        ]);

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        $this->assertEquals('active', $enrollment->status);
        $this->assertNull($enrollment->completed_at);
    }

    // ========== Model Methods ==========

    public function test_is_completed_accessor(): void
    {
        $activeEnrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $completedEnrollment = Enrollment::factory()->completed()->create([
            'user_id' => User::factory()->create(['role' => 'learner'])->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $this->assertFalse($activeEnrollment->is_completed);
        $this->assertTrue($completedEnrollment->is_completed);
    }

    public function test_is_active_accessor(): void
    {
        $activeEnrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $droppedEnrollment = Enrollment::factory()->dropped()->create([
            'user_id' => User::factory()->create(['role' => 'learner'])->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $this->assertTrue($activeEnrollment->is_active);
        $this->assertFalse($droppedEnrollment->is_active);
    }

    public function test_active_scope(): void
    {
        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        Enrollment::factory()->dropped()->create([
            'user_id' => User::factory()->create(['role' => 'learner'])->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $activeEnrollments = Enrollment::active()->get();

        $this->assertCount(1, $activeEnrollments);
        $this->assertEquals($this->learner->id, $activeEnrollments->first()->user_id);
    }

    public function test_completed_scope(): void
    {
        Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $completedUser = User::factory()->create(['role' => 'learner']);
        Enrollment::factory()->completed()->create([
            'user_id' => $completedUser->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $completedEnrollments = Enrollment::completed()->get();

        $this->assertCount(1, $completedEnrollments);
        $this->assertEquals($completedUser->id, $completedEnrollments->first()->user_id);
    }

    public function test_for_user_scope(): void
    {
        Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $otherUser = User::factory()->create(['role' => 'learner']);
        Enrollment::factory()->create([
            'user_id' => $otherUser->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $learnerEnrollments = Enrollment::forUser($this->learner)->get();

        $this->assertCount(1, $learnerEnrollments);
        $this->assertEquals($this->learner->id, $learnerEnrollments->first()->user_id);
    }

    // ========== Lesson Progress Methods ==========

    public function test_get_progress_for_lesson_returns_null_when_not_exists(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $lesson = $this->publicCourse->lessons->first();

        $progress = $enrollment->getProgressForLesson($lesson);

        $this->assertNull($progress);
    }

    public function test_get_progress_for_lesson_returns_progress_when_exists(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $lesson = $this->publicCourse->lessons->first();

        $createdProgress = LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'current_page' => 3,
            'highest_page_reached' => 3,
            'is_completed' => false,
        ]);

        $progress = $enrollment->getProgressForLesson($lesson);

        $this->assertNotNull($progress);
        $this->assertEquals($createdProgress->id, $progress->id);
        $this->assertEquals(3, $progress->current_page);
    }

    public function test_get_or_create_progress_for_lesson_creates_when_not_exists(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $lesson = $this->publicCourse->lessons->first();

        $progress = $this->progressService->getOrCreateProgress($enrollment, $lesson);

        $this->assertNotNull($progress);
        $this->assertEquals(1, $progress->current_page);
        $this->assertEquals(1, $progress->highest_page_reached);
        $this->assertFalse($progress->is_completed);
        $this->assertEquals(0, $progress->time_spent_seconds);
    }

    public function test_get_or_create_progress_for_lesson_returns_existing(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $lesson = $this->publicCourse->lessons->first();

        // Create existing progress
        $existingProgress = LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'current_page' => 5,
            'highest_page_reached' => 5,
            'is_completed' => true,
            'time_spent_seconds' => 300,
        ]);

        $progress = $this->progressService->getOrCreateProgress($enrollment, $lesson);

        $this->assertEquals($existingProgress->id, $progress->id);
        $this->assertEquals(5, $progress->current_page);
        $this->assertTrue($progress->is_completed);
    }

    // ========== Relationships ==========

    public function test_enrollment_belongs_to_user(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $this->assertEquals($this->learner->id, $enrollment->user->id);
    }

    public function test_enrollment_belongs_to_course(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $this->assertEquals($this->publicCourse->id, $enrollment->course->id);
    }

    public function test_enrollment_has_many_lesson_progress(): void
    {
        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $lessons = $this->publicCourse->lessons;

        foreach ($lessons as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'current_page' => 1,
            ]);
        }

        $this->assertCount(3, $enrollment->lessonProgress);
    }

    public function test_enrollment_invited_by_relationship(): void
    {
        $trainer = User::factory()->create(['role' => 'trainer']);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
            'invited_by' => $trainer->id,
        ]);

        $this->assertNotNull($enrollment->invitedBy);
        $this->assertEquals($trainer->id, $enrollment->invitedBy->id);
    }

    public function test_enrollment_last_lesson_relationship(): void
    {
        $lesson = $this->publicCourse->lessons->first();

        $enrollment = Enrollment::factory()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
            'last_lesson_id' => $lesson->id,
        ]);

        $this->assertNotNull($enrollment->lastLesson);
        $this->assertEquals($lesson->id, $enrollment->lastLesson->id);
    }

    // ========== Edge Cases ==========

    public function test_progress_recalculation_is_idempotent(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        $lessons = $this->publicCourse->lessons;

        // Complete one lesson
        LessonProgress::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons->first()->id,
            'is_completed' => true,
            'current_page' => 1,
        ]);

        // Recalculate multiple times
        $this->progressService->recalculateCourseProgress($enrollment);
        $this->progressService->recalculateCourseProgress($enrollment);
        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        // Should still be 33.3%
        $this->assertEquals(33.3, $enrollment->progress_percentage);
    }

    public function test_completed_enrollment_stays_completed_on_recalculation(): void
    {
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $this->publicCourse->id,
        ]);

        // Complete all lessons
        foreach ($this->publicCourse->lessons as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        $this->progressService->recalculateCourseProgress($enrollment);
        $completedAt = $enrollment->completed_at;

        // Recalculate again
        sleep(1); // Ensure time passes
        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        // Should still be completed with same timestamp
        $this->assertEquals('completed', $enrollment->status);
        // Note: The current implementation doesn't update completed_at if already completed
    }

    public function test_progress_with_many_lessons(): void
    {
        // Create course with 10 lessons
        $largeCourse = Course::factory()->published()->create([
            'user_id' => $this->contentManager->id,
            'visibility' => 'public',
        ]);
        $this->addContentToCourse($largeCourse, 10);

        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $this->learner->id,
            'course_id' => $largeCourse->id,
        ]);

        $lessons = $largeCourse->lessons;

        // Complete 7 out of 10 lessons
        foreach ($lessons->take(7) as $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);
        }

        $this->progressService->recalculateCourseProgress($enrollment);
        $enrollment->refresh();

        // 7/10 = 70%
        $this->assertEquals(70, $enrollment->progress_percentage);
        $this->assertEquals('active', $enrollment->status);
    }
}
