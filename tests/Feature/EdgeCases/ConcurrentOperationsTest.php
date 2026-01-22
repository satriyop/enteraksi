<?php

/**
 * Concurrent Operations Tests
 *
 * Ensures the system handles concurrent operations gracefully:
 * - Duplicate enrollment prevention
 * - Concurrent progress updates
 * - Assessment attempt isolation
 */

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

describe('Concurrent Operations', function () {

    describe('Enrollment Concurrency', function () {

        it('prevents duplicate enrollment via unique constraint', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Create first enrollment
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Attempting to create duplicate should fail
            expect(fn () => Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]))->toThrow(\Illuminate\Database\QueryException::class);

            // Verify only one enrollment exists
            expect(Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->count())->toBe(1);
        });

        it('multiple learners can enroll in same course simultaneously', function () {
            $course = Course::factory()->published()->public()->create();
            $learners = User::factory()->count(10)->create(['role' => 'learner']);

            // Enroll all learners
            foreach ($learners as $learner) {
                Enrollment::factory()->active()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);
            }

            // All enrollments should exist
            expect(Enrollment::where('course_id', $course->id)->count())->toBe(10);
        });

        it('enrollment status can be updated independently', function () {
            $course = Course::factory()->published()->public()->create();
            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            $enrollment1 = Enrollment::factory()->active()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
            ]);

            $enrollment2 = Enrollment::factory()->active()->create([
                'user_id' => $learner2->id,
                'course_id' => $course->id,
            ]);

            // Update one enrollment
            $enrollment1->update(['progress_percentage' => 50]);

            // Other enrollment should be unaffected
            expect($enrollment1->refresh()->progress_percentage)->toBe(50);
            expect($enrollment2->refresh()->progress_percentage)->toBe(0);
        });

    });

    describe('Progress Isolation', function () {

        it('lesson progress is isolated between enrollments', function () {
            $course = Course::factory()->published()->public()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            $enrollment1 = Enrollment::factory()->active()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
            ]);

            $enrollment2 = Enrollment::factory()->active()->create([
                'user_id' => $learner2->id,
                'course_id' => $course->id,
            ]);

            // Create progress for learner 1 only
            LessonProgress::create([
                'enrollment_id' => $enrollment1->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 5,
            ]);

            // Verify isolation
            expect(LessonProgress::where('enrollment_id', $enrollment1->id)->count())->toBe(1);
            expect(LessonProgress::where('enrollment_id', $enrollment2->id)->count())->toBe(0);
        });

        it('progress updates do not affect other users', function () {
            $course = Course::factory()->published()->public()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $learners = User::factory()->count(5)->create(['role' => 'learner']);
            $enrollments = [];

            foreach ($learners as $learner) {
                $enrollments[] = Enrollment::factory()->active()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);
            }

            // Each learner creates their own progress
            foreach ($enrollments as $index => $enrollment) {
                LessonProgress::create([
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                    'is_completed' => $index % 2 === 0, // Alternate completed status
                    'current_page' => $index + 1,
                ]);
            }

            // Verify each has their own progress
            expect(LessonProgress::count())->toBe(5);

            // Verify individual progress values
            foreach ($enrollments as $index => $enrollment) {
                $progress = LessonProgress::where('enrollment_id', $enrollment->id)->first();
                expect($progress->current_page)->toBe($index + 1);
                expect($progress->is_completed)->toBe($index % 2 === 0);
            }
        });

    });

    describe('Assessment Attempt Isolation', function () {

        it('multiple learners can attempt same assessment independently', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'max_attempts' => 3,
            ]);

            $learners = User::factory()->count(5)->create(['role' => 'learner']);

            foreach ($learners as $learner) {
                Enrollment::factory()->active()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);

                AssessmentAttempt::factory()->submitted()->create([
                    'assessment_id' => $assessment->id,
                    'user_id' => $learner->id,
                    'attempt_number' => 1,
                ]);
            }

            // All attempts should exist independently
            expect(AssessmentAttempt::where('assessment_id', $assessment->id)->count())->toBe(5);
        });

        it('attempt limits are tracked per user', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'max_attempts' => 2,
            ]);

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->active()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner2->id,
                'course_id' => $course->id,
            ]);

            // Learner 1 uses both attempts
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner1->id,
                'attempt_number' => 1,
            ]);

            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner1->id,
                'attempt_number' => 2,
            ]);

            // Learner 1 cannot attempt anymore
            expect($assessment->canBeAttemptedBy($learner1))->toBeFalse();

            // Learner 2 can still attempt
            expect($assessment->canBeAttemptedBy($learner2))->toBeTrue();
        });

        it('grading one attempt does not affect others', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->active()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner2->id,
                'course_id' => $course->id,
            ]);

            $attempt1 = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner1->id,
                'score' => null,
            ]);

            $attempt2 = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner2->id,
                'score' => null,
            ]);

            // Grade attempt 1
            $attempt1->update([
                'score' => 85,
                'max_score' => 100,
                'percentage' => 85.0,
                'passed' => true,
                'status' => 'graded',
            ]);

            // Attempt 2 should remain ungraded
            $attempt1->refresh();
            $attempt2->refresh();

            // Check status (could be string or state object)
            $status1 = is_string($attempt1->status) ? $attempt1->status : $attempt1->status->getValue();
            $status2 = is_string($attempt2->status) ? $attempt2->status : $attempt2->status->getValue();

            expect($status1)->toBe('graded');
            expect($status2)->toBe('submitted');
            expect($attempt2->score)->toBeNull();
        });

    });

    describe('Course Enrollment Independence', function () {

        it('enrolling in one course does not affect other courses', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course1 = Course::factory()->published()->public()->create();
            $course2 = Course::factory()->published()->public()->create();

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course1->id,
                'progress_percentage' => 50,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course2->id,
                'progress_percentage' => 25,
            ]);

            // Update course 1 progress
            Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course1->id)
                ->update(['progress_percentage' => 75]);

            // Verify independence
            $enrollment1 = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course1->id)->first();
            $enrollment2 = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course2->id)->first();

            expect($enrollment1->progress_percentage)->toBe(75);
            expect($enrollment2->progress_percentage)->toBe(25);
        });

        it('dropping one course does not affect other enrollments', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course1 = Course::factory()->published()->public()->create();
            $course2 = Course::factory()->published()->public()->create();

            $enrollment1 = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course1->id,
            ]);

            $enrollment2 = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course2->id,
            ]);

            // Drop course 1
            $this->actingAs($learner)
                ->delete(route('courses.unenroll', $course1))
                ->assertRedirect();

            // Verify states
            expect($enrollment1->refresh()->status->getValue())->toBe('dropped');
            expect($enrollment2->refresh()->status->getValue())->toBe('active');
        });

    });

});
