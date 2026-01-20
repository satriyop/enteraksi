<?php

/**
 * Data Isolation Tests
 *
 * Ensures data is properly isolated between:
 * - Users (enrollments, progress, attempts)
 * - Courses (sections, lessons, assessments)
 * - Content managers (statistics, courses)
 */

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\User;

describe('Data Isolation', function () {

    describe('User Data Isolation', function () {

        it('enrollment data is isolated between users', function () {
            $course = Course::factory()->published()->public()->create();

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            // Create enrollments with different progress
            $enrollment1 = Enrollment::factory()->active()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
                'progress_percentage' => 75,
            ]);

            $enrollment2 = Enrollment::factory()->active()->create([
                'user_id' => $learner2->id,
                'course_id' => $course->id,
                'progress_percentage' => 25,
            ]);

            // Each learner sees only their own enrollment
            $this->actingAs($learner1)
                ->get(route('learner.dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('myLearning', 1)
                    ->where('myLearning.0.progress_percentage', 75)
                );

            $this->actingAs($learner2)
                ->get(route('learner.dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('myLearning', 1)
                    ->where('myLearning.0.progress_percentage', 25)
                );
        });

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

            // Learner 1 makes progress
            LessonProgress::create([
                'enrollment_id' => $enrollment1->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 5,
            ]);

            // Verify learner 1 has progress
            expect(LessonProgress::where('enrollment_id', $enrollment1->id)->count())->toBe(1);
            expect(LessonProgress::where('enrollment_id', $enrollment1->id)->first()->is_completed)->toBeTrue();

            // Verify learner 2 has NO progress
            expect(LessonProgress::where('enrollment_id', $enrollment2->id)->count())->toBe(0);
        });

        it('assessment attempts are isolated between users', function () {
            $course = Course::factory()->published()->public()->create();
            $assessment = Assessment::factory()->published()->create(['course_id' => $course->id]);

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

            // Create attempt for learner 1
            $attempt1 = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner1->id,
                'score' => 85,
            ]);

            // Learner 1 can view own attempt
            $this->actingAs($learner1)
                ->get(route('assessments.attempt.complete', [$course, $assessment, $attempt1]))
                ->assertOk();

            // Learner 2 cannot view learner 1's attempt
            $this->actingAs($learner2)
                ->get(route('assessments.attempt.complete', [$course, $assessment, $attempt1]))
                ->assertForbidden();

            // Verify attempt counts
            expect(AssessmentAttempt::where('user_id', $learner1->id)->count())->toBe(1);
            expect(AssessmentAttempt::where('user_id', $learner2->id)->count())->toBe(0);
        });

        it('course ratings are isolated to enrolled learners', function () {
            $course = Course::factory()->published()->public()->create();

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);
            $learner3 = User::factory()->create(['role' => 'learner']);

            // Only learner 1 and 2 are enrolled
            Enrollment::factory()->completed()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
            ]);

            Enrollment::factory()->completed()->create([
                'user_id' => $learner2->id,
                'course_id' => $course->id,
            ]);

            // Learner 1 can rate
            $this->actingAs($learner1)
                ->post(route('courses.ratings.store', $course), [
                    'rating' => 5,
                    'review' => 'Excellent course!',
                ])
                ->assertRedirect();

            // Learner 2 can rate independently
            $this->actingAs($learner2)
                ->post(route('courses.ratings.store', $course), [
                    'rating' => 4,
                    'review' => 'Good course',
                ])
                ->assertRedirect();

            // Learner 3 (not enrolled) cannot rate
            $this->actingAs($learner3)
                ->post(route('courses.ratings.store', $course), [
                    'rating' => 5,
                    'review' => 'Fake rating',
                ])
                ->assertForbidden();

            // Verify ratings
            expect(CourseRating::where('course_id', $course->id)->count())->toBe(2);
            expect(CourseRating::where('user_id', $learner1->id)->first()->rating)->toBe(5);
            expect(CourseRating::where('user_id', $learner2->id)->first()->rating)->toBe(4);
        });

    });

    describe('Course Data Isolation', function () {

        it('sections belong to single course', function () {
            $course1 = Course::factory()->draft()->create();
            $course2 = Course::factory()->draft()->create();

            $section1 = CourseSection::factory()->create(['course_id' => $course1->id]);
            $section2 = CourseSection::factory()->create(['course_id' => $course2->id]);

            // Verify each course only has its own sections
            expect($course1->sections()->count())->toBe(1);
            expect($course2->sections()->count())->toBe(1);

            expect($course1->sections->first()->id)->toBe($section1->id);
            expect($course2->sections->first()->id)->toBe($section2->id);
        });

        it('lessons belong to single section', function () {
            $course = Course::factory()->draft()->create();

            $section1 = CourseSection::factory()->create(['course_id' => $course->id]);
            $section2 = CourseSection::factory()->create(['course_id' => $course->id]);

            $lesson1 = Lesson::factory()->create(['course_section_id' => $section1->id]);
            $lesson2 = Lesson::factory()->create(['course_section_id' => $section2->id]);

            // Verify each section only has its own lessons
            expect($section1->lessons()->count())->toBe(1);
            expect($section2->lessons()->count())->toBe(1);

            expect($section1->lessons->first()->id)->toBe($lesson1->id);
            expect($section2->lessons->first()->id)->toBe($lesson2->id);
        });

        it('assessments belong to single course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course1 = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $course2 = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $assessment1 = Assessment::factory()->create([
                'course_id' => $course1->id,
                'user_id' => $cm->id,
            ]);

            $assessment2 = Assessment::factory()->create([
                'course_id' => $course2->id,
                'user_id' => $cm->id,
            ]);

            // Verify each course only has its own assessments
            expect($course1->assessments()->count())->toBe(1);
            expect($course2->assessments()->count())->toBe(1);

            expect($course1->assessments->first()->id)->toBe($assessment1->id);
            expect($course2->assessments->first()->id)->toBe($assessment2->id);
        });

        it('questions belong to single assessment', function () {
            $course = Course::factory()->draft()->create();

            $assessment1 = Assessment::factory()->create(['course_id' => $course->id]);
            $assessment2 = Assessment::factory()->create(['course_id' => $course->id]);

            Question::factory()->create(['assessment_id' => $assessment1->id]);
            Question::factory()->create(['assessment_id' => $assessment1->id]);
            Question::factory()->create(['assessment_id' => $assessment2->id]);

            // Verify each assessment only has its own questions
            expect($assessment1->questions()->count())->toBe(2);
            expect($assessment2->questions()->count())->toBe(1);
        });

    });

    describe('Content Manager Isolation', function () {

        it('CM can only see own courses in management index', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);

            // Each CM creates their own courses
            $course1 = Course::factory()->draft()->create(['user_id' => $cm1->id, 'title' => 'CM1 Course']);
            $course2 = Course::factory()->published()->public()->create(['user_id' => $cm1->id, 'title' => 'CM1 Published']);
            $course3 = Course::factory()->draft()->create(['user_id' => $cm2->id, 'title' => 'CM2 Course']);

            // CM1 sees only their own courses
            $this->actingAs($cm1)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 2) // Only CM1's 2 courses
                );

            // CM2 sees only their own course
            $this->actingAs($cm2)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 1) // Only CM2's 1 course
                );
        });

        it('CM cannot edit other CMs draft course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create([
                'user_id' => $cm2->id,
                'title' => 'Original Title',
            ]);

            // CM1 cannot edit CM2's course
            $this->actingAs($cm1)
                ->patch(route('courses.update', $course), [
                    'title' => 'Hacked Title',
                    'short_description' => 'Hacked',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertForbidden();

            // Verify title unchanged
            expect($course->refresh()->title)->toBe('Original Title');
        });

        it('CM cannot delete other CMs section', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // CM1 cannot delete CM2's section
            $this->actingAs($cm1)
                ->delete(route('sections.destroy', $section))
                ->assertForbidden();

            // Verify section still exists
            $this->assertNotSoftDeleted('course_sections', ['id' => $section->id]);
        });

        it('CM cannot access other CMs assessment attempts for grading', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm2->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm2->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // CM1 cannot view CM2's assessment attempts
            $this->actingAs($cm1)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertForbidden();
        });

    });

    describe('Cross-Course Progress Isolation', function () {

        it('learner progress in course A is independent of course B', function () {
            $learner = User::factory()->create(['role' => 'learner']);

            $courseA = Course::factory()->published()->public()->create();
            $sectionA = CourseSection::factory()->create(['course_id' => $courseA->id]);
            Lesson::factory()->count(3)->create(['course_section_id' => $sectionA->id]);

            $courseB = Course::factory()->published()->public()->create();
            $sectionB = CourseSection::factory()->create(['course_id' => $courseB->id]);
            Lesson::factory()->count(5)->create(['course_section_id' => $sectionB->id]);

            // Enroll in both courses
            $enrollmentA = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $courseA->id,
                'progress_percentage' => 66, // 2/3 lessons complete
            ]);

            $enrollmentB = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $courseB->id,
                'progress_percentage' => 20, // 1/5 lessons complete
            ]);

            // Verify progress is independent
            expect($enrollmentA->progress_percentage)->toBe(66);
            expect($enrollmentB->progress_percentage)->toBe(20);

            // Dashboard should show both with correct progress
            $this->actingAs($learner)
                ->get(route('learner.dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('myLearning', 2)
                );
        });

        it('assessment attempts in course A do not affect course B limits', function () {
            $learner = User::factory()->create(['role' => 'learner']);

            $courseA = Course::factory()->published()->public()->create();
            $assessmentA = Assessment::factory()->published()->create([
                'course_id' => $courseA->id,
                'max_attempts' => 2,
            ]);

            $courseB = Course::factory()->published()->public()->create();
            $assessmentB = Assessment::factory()->published()->create([
                'course_id' => $courseB->id,
                'max_attempts' => 2,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $courseA->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $courseB->id,
            ]);

            // Use both attempts on course A
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessmentA->id,
                'user_id' => $learner->id,
                'attempt_number' => 1,
            ]);

            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessmentA->id,
                'user_id' => $learner->id,
                'attempt_number' => 2,
            ]);

            // Should still be able to attempt course B assessment
            expect($assessmentB->canBeAttemptedBy($learner))->toBeTrue();

            // But cannot attempt course A assessment anymore
            expect($assessmentA->canBeAttemptedBy($learner))->toBeFalse();
        });

    });

});
