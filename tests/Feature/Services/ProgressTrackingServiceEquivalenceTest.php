<?php

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ProgressTrackingService equivalence to deprecated methods', function () {

    describe('recalculateCourseProgress()', function () {

        it('calculates progress based on completed lessons', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lessons = Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress_percentage' => 0,
            ]);

            // Complete 2 of 3 lessons
            foreach ($lessons->take(2) as $lesson) {
                $enrollment->lessonProgress()->create([
                    'lesson_id' => $lesson->id,
                    'user_id' => $user->id,
                    'progress_percentage' => 100,
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
            }

            $service = progressService();
            $percentage = $service->recalculateCourseProgress($enrollment);

            // 2 of 3 lessons = 66.67% (rounded)
            expect($percentage)->toBeGreaterThan(60);
            expect($percentage)->toBeLessThan(70);

            $enrollment->refresh();
            expect($enrollment->progress_percentage)->toBeGreaterThan(60);
        });

        it('sets progress to 100% when all lessons completed', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lessons = Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress_percentage' => 0,
            ]);

            // Complete all lessons
            foreach ($lessons as $lesson) {
                $enrollment->lessonProgress()->create([
                    'lesson_id' => $lesson->id,
                    'user_id' => $user->id,
                    'progress_percentage' => 100,
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
            }

            $service = progressService();
            $percentage = $service->recalculateCourseProgress($enrollment);

            expect($percentage)->toBe(100.0);
        });

        it('handles courses with no lessons', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress_percentage' => 0,
            ]);

            $service = progressService();
            $percentage = $service->recalculateCourseProgress($enrollment);

            // Lesson-based calculator returns 0% for no lessons
            // This is because there's nothing to complete (0/0 = 0)
            expect($percentage)->toBe(0.0);
        });
    });

    describe('getOrCreateProgress()', function () {

        it('creates new progress record when none exists', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            expect($enrollment->lessonProgress()->count())->toBe(0);

            $service = progressService();
            $progress = $service->getOrCreateProgress($enrollment, $lesson);

            expect($progress)->not->toBeNull();
            expect($progress)->toBeInstanceOf(LessonProgress::class);
            expect($progress->lesson_id)->toBe($lesson->id);
            expect($progress->enrollment_id)->toBe($enrollment->id);
            expect($progress->is_completed)->toBeFalse();
        });

        it('returns existing progress record when one exists', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Create existing progress with values we can verify
            $existingProgress = $enrollment->lessonProgress()->create([
                'lesson_id' => $lesson->id,
                'current_page' => 5,
                'highest_page_reached' => 5,
                'time_spent_seconds' => 120,
            ]);

            $service = progressService();
            $progress = $service->getOrCreateProgress($enrollment, $lesson);

            expect($progress->id)->toBe($existingProgress->id);
            expect((int) $progress->current_page)->toBe(5);
            expect((int) $progress->time_spent_seconds)->toBe(120);
        });

        it('creates progress with correct default values', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $service = progressService();
            $progress = $service->getOrCreateProgress($enrollment, $lesson);

            expect((int) $progress->current_page)->toBe(1);
            expect((int) $progress->highest_page_reached)->toBe(1);
            expect((int) $progress->time_spent_seconds)->toBe(0);
            expect($progress->is_completed)->toBeFalse();
        });
    });

    describe('isEnrollmentComplete()', function () {

        it('returns false when not all lessons completed', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lessons = Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Complete only 2 lessons
            foreach ($lessons->take(2) as $lesson) {
                $enrollment->lessonProgress()->create([
                    'lesson_id' => $lesson->id,
                    'user_id' => $user->id,
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
            }

            $service = progressService();
            expect($service->isEnrollmentComplete($enrollment))->toBeFalse();
        });

        it('returns true when all lessons completed', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lessons = Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // Complete all lessons
            foreach ($lessons as $lesson) {
                $enrollment->lessonProgress()->create([
                    'lesson_id' => $lesson->id,
                    'user_id' => $user->id,
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
            }

            $service = progressService();
            expect($service->isEnrollmentComplete($enrollment))->toBeTrue();
        });
    });
});
