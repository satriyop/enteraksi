<?php

/**
 * Data Integrity Tests
 *
 * Ensures data integrity across the application:
 * - Soft delete behavior
 * - Cascade delete behavior
 * - Timestamp integrity
 * - Foreign key relationships
 */

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

describe('Data Integrity', function () {

    describe('Soft Delete Behavior', function () {

        it('soft deleted course is hidden from queries', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            // Course exists in normal query
            expect(Course::where('id', $course->id)->exists())->toBeTrue();

            // Delete the course
            $course->delete();

            // Course no longer in normal query
            expect(Course::where('id', $course->id)->exists())->toBeFalse();

            // But exists in withTrashed query
            expect(Course::withTrashed()->where('id', $course->id)->exists())->toBeTrue();
        });

        it('soft deleted section is hidden from course sections', function () {
            $course = Course::factory()->draft()->create();
            $section1 = CourseSection::factory()->create(['course_id' => $course->id]);
            $section2 = CourseSection::factory()->create(['course_id' => $course->id]);

            expect($course->sections()->count())->toBe(2);

            // Delete one section
            $section1->delete();

            // Only one section visible
            expect($course->sections()->count())->toBe(1);
            expect($course->sections->first()->id)->toBe($section2->id);

            // Both visible with trashed
            expect($course->sections()->withTrashed()->count())->toBe(2);
        });

        it('soft deleted lesson is hidden from section lessons', function () {
            $course = Course::factory()->draft()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson1 = Lesson::factory()->create(['course_section_id' => $section->id]);
            $lesson2 = Lesson::factory()->create(['course_section_id' => $section->id]);

            expect($section->lessons()->count())->toBe(2);

            $lesson1->delete();

            expect($section->lessons()->count())->toBe(1);
            expect($section->lessons()->withTrashed()->count())->toBe(2);
        });

        it('soft deleted assessment is hidden from course assessments', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment1 = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);
            $assessment2 = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            expect($course->assessments()->count())->toBe(2);

            $assessment1->delete();

            expect($course->assessments()->count())->toBe(1);
            expect($course->assessments()->withTrashed()->count())->toBe(2);
        });

    });

    describe('Cascade Soft Delete', function () {

        it('deleting section cascades soft delete to lessons', function () {
            $course = Course::factory()->draft()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson1 = Lesson::factory()->create(['course_section_id' => $section->id]);
            $lesson2 = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Verify lessons exist
            expect(Lesson::where('course_section_id', $section->id)->count())->toBe(2);

            // Delete section
            $section->delete();

            // Lessons should be soft deleted
            expect(Lesson::where('course_section_id', $section->id)->count())->toBe(0);
            expect(Lesson::withTrashed()->where('course_section_id', $section->id)->count())->toBe(2);

            // Verify soft deleted
            $this->assertSoftDeleted('lessons', ['id' => $lesson1->id]);
            $this->assertSoftDeleted('lessons', ['id' => $lesson2->id]);
        });

        it('restoring section restores cascaded lessons', function () {
            $course = Course::factory()->draft()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson1 = Lesson::factory()->create(['course_section_id' => $section->id]);
            $lesson2 = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Delete section (cascades to lessons)
            $section->delete();

            // Verify both deleted
            $this->assertSoftDeleted('course_sections', ['id' => $section->id]);
            $this->assertSoftDeleted('lessons', ['id' => $lesson1->id]);

            // Restore section
            $section->restore();

            // Lessons should be restored
            $this->assertNotSoftDeleted('course_sections', ['id' => $section->id]);
            $this->assertNotSoftDeleted('lessons', ['id' => $lesson1->id]);
            $this->assertNotSoftDeleted('lessons', ['id' => $lesson2->id]);
        });

    });

    describe('Preservation After Parent Delete', function () {

        it('enrollments preserved when course is soft deleted', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Delete course
            $course->delete();

            // Enrollment should still exist (for historical records)
            expect(Enrollment::where('id', $enrollment->id)->exists())->toBeTrue();
            expect($enrollment->refresh()->course_id)->toBe($course->id);
        });

        it('lesson progress preserved when lesson is soft deleted', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $progress = LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
            ]);

            // Delete lesson
            $lesson->delete();

            // Progress record should still exist
            expect(LessonProgress::where('id', $progress->id)->exists())->toBeTrue();
        });

        it('assessment attempts preserved when assessment is soft deleted', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // Delete assessment
            $assessment->delete();

            // Attempt should still exist (for historical records)
            expect(AssessmentAttempt::where('id', $attempt->id)->exists())->toBeTrue();
        });

        it('ratings preserved when course is soft deleted', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $rating = CourseRating::create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'rating' => 5,
                'review' => 'Great course!',
            ]);

            // Delete course
            $course->delete();

            // Rating should still exist
            expect(CourseRating::where('id', $rating->id)->exists())->toBeTrue();
        });

    });

    describe('Timestamp Integrity', function () {

        it('enrolled_at is set on enrollment creation', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            expect($enrollment->enrolled_at)->not->toBeNull();
            expect($enrollment->enrolled_at)->toBeInstanceOf(\Carbon\Carbon::class);
        });

        it('completed_at is set when enrollment is completed', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'completed_at' => null,
            ]);

            expect($enrollment->completed_at)->toBeNull();

            // Complete the enrollment
            $completedAt = now();
            $enrollment->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
            ]);

            expect($enrollment->completed_at)->not->toBeNull();
            expect($enrollment->completed_at->toDateTimeString())->toBe($completedAt->toDateTimeString());
        });

        it('published_at is set when course is published', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            expect($course->published_at)->toBeNull();

            $this->actingAs($admin)
                ->post(route('courses.publish', $course))
                ->assertRedirect();

            $course->refresh();

            expect($course->published_at)->not->toBeNull();
            expect($course->published_at)->toBeInstanceOf(\Carbon\Carbon::class);
        });

        it('submitted_at is set when attempt is submitted', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $submittedAt = now();

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'submitted_at' => $submittedAt,
            ]);

            expect($attempt->submitted_at)->not->toBeNull();
            expect($attempt->submitted_at->toDateTimeString())->toBe($submittedAt->toDateTimeString());
        });

    });

    describe('Empty State Handling', function () {

        it('course with no sections has zero lessons', function () {
            $course = Course::factory()->draft()->create();

            expect($course->sections()->count())->toBe(0);
            expect($course->lessons()->count())->toBe(0);
        });

        it('section with no lessons is valid', function () {
            $course = Course::factory()->draft()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            expect($section->lessons()->count())->toBe(0);
            expect($course->sections()->count())->toBe(1);
        });

        it('course with no ratings has null average', function () {
            $course = Course::factory()->published()->public()->create();

            expect($course->ratings()->count())->toBe(0);
            expect($course->ratings()->avg('rating'))->toBeNull();
        });

        it('course with no enrollments is valid', function () {
            $course = Course::factory()->published()->public()->create();

            expect($course->enrollments()->count())->toBe(0);
        });

        it('enrollment with no progress records has zero progress', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'progress_percentage' => 0,
            ]);

            expect(LessonProgress::where('enrollment_id', $enrollment->id)->count())->toBe(0);
            expect($enrollment->progress_percentage)->toBe(0);
        });

    });

});
