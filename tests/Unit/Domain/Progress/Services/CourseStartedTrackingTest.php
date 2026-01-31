<?php

use App\Domain\Enrollment\Events\CourseStarted;
use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Domain\Progress\Services\ProgressTrackingService;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->progressService = app(ProgressTrackingService::class);
});

describe('CourseStarted tracking', function () {
    describe('started_at field', function () {
        it('sets started_at on first progress update', function () {
            Event::fake([CourseStarted::class]);

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'started_at' => null,
            ]);

            expect($enrollment->started_at)->toBeNull();

            // Update progress (first content access)
            $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 1,
                totalPages: 5,
            ));

            $enrollment->refresh();
            expect($enrollment->started_at)->not->toBeNull();

            Event::assertDispatched(CourseStarted::class, function ($event) use ($enrollment) {
                return $event->enrollment->id === $enrollment->id;
            });
        });

        it('does not overwrite started_at on subsequent progress updates', function () {
            Event::fake([CourseStarted::class]);

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $originalStartedAt = now()->subHours(2);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'started_at' => $originalStartedAt,
            ]);

            // Update progress again
            $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 2,
                totalPages: 5,
            ));

            $enrollment->refresh();
            expect($enrollment->started_at->timestamp)->toBe($originalStartedAt->timestamp);

            // Event should NOT be dispatched for already-started course
            Event::assertNotDispatched(CourseStarted::class);
        });

        it('sets started_at when manually completing a lesson', function () {
            Event::fake([CourseStarted::class]);

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'started_at' => null,
            ]);

            // Complete lesson directly (e.g., via force complete button)
            $this->progressService->completeLesson($enrollment, $lesson);

            $enrollment->refresh();
            expect($enrollment->started_at)->not->toBeNull();

            Event::assertDispatched(CourseStarted::class);
        });
    });

    describe('CourseStarted event', function () {
        it('includes time_to_start_hours in metadata', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            $enrolledAt = now()->subHours(5);
            $startedAt = now();

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'enrolled_at' => $enrolledAt,
                'started_at' => $startedAt,
            ]);

            $event = new CourseStarted($enrollment);

            $metadata = $event->getMetadata();

            expect($metadata['enrollment_id'])->toBe($enrollment->id);
            expect($metadata['user_id'])->toBe($user->id);
            expect($metadata['course_id'])->toBe($course->id);
            expect($metadata['time_to_start_hours'])->toBe(5.0);
        });

        it('returns correct event name', function () {
            $enrollment = Enrollment::factory()->create();
            $event = new CourseStarted($enrollment);

            expect($event->getEventName())->toBe('enrollment.course_started');
            expect($event->getAggregateType())->toBe('enrollment');
        });
    });

    describe('media progress', function () {
        it('sets started_at on first media progress update', function () {
            Event::fake([CourseStarted::class]);

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'content_type' => 'video',
            ]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'started_at' => null,
            ]);

            // Update media progress
            $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                mediaPositionSeconds: 30,
                mediaDurationSeconds: 600,
            ));

            $enrollment->refresh();
            expect($enrollment->started_at)->not->toBeNull();

            Event::assertDispatched(CourseStarted::class);
        });
    });
});
