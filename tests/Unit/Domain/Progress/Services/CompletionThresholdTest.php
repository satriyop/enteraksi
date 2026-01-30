<?php

use App\Domain\Progress\DTOs\ProgressUpdateDTO;
use App\Domain\Progress\Services\ProgressTrackingService;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->progressService = app(ProgressTrackingService::class);
});

describe('Configurable completion thresholds', function () {
    describe('media completion threshold', function () {
        it('uses default 90% threshold for media', function () {
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
            ]);

            // At 89% - should NOT complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                mediaPositionSeconds: 89,
                mediaDurationSeconds: 100,
            ));
            expect($result->lessonCompleted)->toBeFalse();

            // At 90% - should complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                mediaPositionSeconds: 90,
                mediaDurationSeconds: 100,
            ));
            expect($result->lessonCompleted)->toBeTrue();
        });

        it('respects custom media threshold from config', function () {
            Config::set('lms.completion_thresholds.media', 80);

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
            ]);

            // At 79% - should NOT complete with 80% threshold
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                mediaPositionSeconds: 79,
                mediaDurationSeconds: 100,
            ));
            expect($result->lessonCompleted)->toBeFalse();

            // At 80% - should complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                mediaPositionSeconds: 80,
                mediaDurationSeconds: 100,
            ));
            expect($result->lessonCompleted)->toBeTrue();
        });
    });

    describe('page completion threshold', function () {
        it('uses default 100% threshold for pages', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'content_type' => 'text',
            ]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // At page 9 of 10 - should NOT complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 9,
                totalPages: 10,
            ));
            expect($result->lessonCompleted)->toBeFalse();

            // At page 10 of 10 - should complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 10,
                totalPages: 10,
            ));
            expect($result->lessonCompleted)->toBeTrue();
        });

        it('respects custom page threshold from config', function () {
            Config::set('lms.completion_thresholds.pages', 80);

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'content_type' => 'text',
            ]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // 10 pages * 80% = 8 pages required
            // At page 7 - should NOT complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 7,
                totalPages: 10,
            ));
            expect($result->lessonCompleted)->toBeFalse();

            // At page 8 - should complete (80% of 10 = 8)
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 8,
                totalPages: 10,
            ));
            expect($result->lessonCompleted)->toBeTrue();
        });

        it('rounds up required pages correctly', function () {
            Config::set('lms.completion_thresholds.pages', 90);

            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'content_type' => 'text',
            ]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            // 7 pages * 90% = 6.3 → rounds up to 7 pages required
            // At page 6 - should NOT complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 6,
                totalPages: 7,
            ));
            expect($result->lessonCompleted)->toBeFalse();

            // At page 7 - should complete
            $result = $this->progressService->updateProgress(new ProgressUpdateDTO(
                enrollmentId: $enrollment->id,
                lessonId: $lesson->id,
                currentPage: 7,
                totalPages: 7,
            ));
            expect($result->lessonCompleted)->toBeTrue();
        });
    });
});
