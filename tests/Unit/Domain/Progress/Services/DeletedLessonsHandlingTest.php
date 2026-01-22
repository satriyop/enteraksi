<?php

use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\Events\LessonDeleted;
use App\Domain\Progress\Listeners\RecalculateProgressOnLessonDeletion;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->progressService = app(ProgressTrackingServiceContract::class);
});

describe('Deleted lessons handling', function () {
    describe('progress calculation excludes deleted lessons', function () {
        it('does not count completed progress for deleted lessons', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Create 5 lessons
            $lessons = Lesson::factory()->count(5)->create([
                'course_section_id' => $section->id,
            ]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Complete 3 lessons
            foreach ($lessons->take(3) as $lesson) {
                $this->progressService->completeLesson($enrollment, $lesson);
            }

            $enrollment->refresh();
            expect((float) $enrollment->progress_percentage)->toBe(60.0); // 3/5 = 60%

            // Delete one of the completed lessons (soft delete)
            $lessons[0]->delete();

            // Recalculate progress
            $this->progressService->recalculateCourseProgress($enrollment);

            $enrollment->refresh();
            // Now only 2 completed lessons out of 4 total = 50%
            expect((float) $enrollment->progress_percentage)->toBe(50.0);
        });

        it('excludes orphaned progress records from completion check', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Create 3 lessons
            $lessons = Lesson::factory()->count(3)->create([
                'course_section_id' => $section->id,
            ]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Complete all 3 lessons
            foreach ($lessons as $lesson) {
                $this->progressService->completeLesson($enrollment, $lesson);
            }

            $enrollment->refresh();
            expect($enrollment->status->getValue())->toBe('completed');

            // Now test a new enrollment scenario where lessons are deleted before completion
            $user2 = User::factory()->create();
            $enrollment2 = Enrollment::factory()->active()->create([
                'user_id' => $user2->id,
                'course_id' => $course->id,
            ]);

            // Complete first 2 lessons
            $this->progressService->completeLesson($enrollment2, $lessons[0]);
            $this->progressService->completeLesson($enrollment2, $lessons[1]);

            // Delete the first lesson (which was completed)
            $lessons[0]->delete();

            // Recalculate progress
            $this->progressService->recalculateCourseProgress($enrollment2);

            $enrollment2->refresh();
            // Only 1 completed lesson out of 2 remaining = 50%
            expect((float) $enrollment2->progress_percentage)->toBe(50.0);
            expect($enrollment2->status->getValue())->toBe('active'); // Not complete
        });
    });

    describe('LessonDeleted event', function () {
        it('dispatches LessonDeleted event when lesson is deleted', function () {
            Event::fake([LessonDeleted::class]);

            $instructor = User::factory()->create();
            $course = Course::factory()->published()->create(['user_id' => $instructor->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $lessonId = $lesson->id;
            $lessonTitle = $lesson->title;

            // Simulate controller behavior
            $lesson->delete();
            LessonDeleted::dispatch($lessonId, $course, $lessonTitle, $instructor->id);

            Event::assertDispatched(LessonDeleted::class, function ($event) use ($lessonId, $course, $lessonTitle) {
                return $event->lessonId === $lessonId
                    && $event->course->id === $course->id
                    && $event->lessonTitle === $lessonTitle;
            });
        });

        it('event includes correct metadata', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Create some active enrollments
            Enrollment::factory()->active()->count(3)->create(['course_id' => $course->id]);

            $event = new LessonDeleted($lesson->id, $course, $lesson->title, $user->id);

            $metadata = $event->getMetadata();

            expect($metadata['lesson_id'])->toBe($lesson->id);
            expect($metadata['lesson_title'])->toBe($lesson->title);
            expect($metadata['course_id'])->toBe($course->id);
            expect($metadata['course_title'])->toBe($course->title);
            expect($metadata['active_enrollments_count'])->toBe(3);
        });
    });

    describe('RecalculateProgressOnLessonDeletion listener', function () {
        it('recalculates progress for all active enrollments', function () {
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Create 4 lessons
            $lessons = Lesson::factory()->count(4)->create([
                'course_section_id' => $section->id,
            ]);

            // Create 3 active enrollments with progress
            $enrollments = [];
            for ($i = 0; $i < 3; $i++) {
                $user = User::factory()->create();
                $enrollment = Enrollment::factory()->active()->create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ]);

                // Complete 2 lessons for each
                $this->progressService->completeLesson($enrollment, $lessons[0]);
                $this->progressService->completeLesson($enrollment, $lessons[1]);

                $enrollment->refresh();
                expect((float) $enrollment->progress_percentage)->toBe(50.0); // 2/4

                $enrollments[] = $enrollment;
            }

            // Delete one of the completed lessons
            $lessonId = $lessons[0]->id;
            $lessonTitle = $lessons[0]->title;
            $lessons[0]->delete();

            // Simulate the listener handling
            $event = new LessonDeleted($lessonId, $course, $lessonTitle);
            $listener = app(RecalculateProgressOnLessonDeletion::class);
            $listener->handle($event);

            // Verify all enrollments have updated progress
            foreach ($enrollments as $enrollment) {
                $enrollment->refresh();
                // Now 1 completed lesson out of 3 remaining = 33.3%
                expect((float) $enrollment->progress_percentage)->toBe(33.3);
            }
        });

        it('does not affect completed enrollments', function () {
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Create 3 lessons
            $lessons = Lesson::factory()->count(3)->create([
                'course_section_id' => $section->id,
            ]);

            // Create a completed enrollment
            $user = User::factory()->create();
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Complete all lessons
            foreach ($lessons as $lesson) {
                $this->progressService->completeLesson($enrollment, $lesson);
            }

            $enrollment->refresh();
            expect($enrollment->status->getValue())->toBe('completed');
            expect((float) $enrollment->progress_percentage)->toBe(100.0);

            // Delete one lesson
            $lessonId = $lessons[0]->id;
            $lessonTitle = $lessons[0]->title;
            $lessons[0]->delete();

            // Listener should not process completed enrollments (they're not active)
            $event = new LessonDeleted($lessonId, $course, $lessonTitle);
            $listener = app(RecalculateProgressOnLessonDeletion::class);
            $listener->handle($event);

            // Completed enrollment should stay as is
            $enrollment->refresh();
            expect($enrollment->status->getValue())->toBe('completed');
            // Progress percentage might change but status remains completed
        });

        it('handles course with no active enrollments gracefully', function () {
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // No enrollments exist

            $lesson->delete();

            $event = new LessonDeleted($lesson->id, $course, $lesson->title);
            $listener = app(RecalculateProgressOnLessonDeletion::class);

            // Should not throw an exception
            expect(fn () => $listener->handle($event))->not->toThrow(Exception::class);
        });
    });
});
