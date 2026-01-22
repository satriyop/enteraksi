<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;

/**
 * Status-Based Restrictions Tests
 *
 * These tests verify that different statuses (course status, enrollment status,
 * assessment status, attempt status) properly restrict or allow actions.
 */
describe('Status-Based Restrictions', function () {

    describe('Draft Course Restrictions', function () {

        it('learner cannot view draft course details', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($learner)
                ->get(route('courses.show', $course))
                ->assertForbidden();
        });

        it('cannot enroll in draft course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->draft()->create([
                'user_id' => $cm->id,
                'visibility' => 'public',
            ]);

            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertForbidden();
        });

        it('course owner can view their draft course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->get(route('courses.show', $course))
                ->assertOk();
        });

        it('admin can view any draft course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->get(route('courses.show', $course))
                ->assertOk();
        });

    });

    describe('Archived Course Restrictions', function () {

        it('cannot enroll in archived course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->create([
                'user_id' => $cm->id,
                'status' => 'archived',
                'visibility' => 'public',
            ]);

            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertForbidden();
        });

        it('existing enrollments preserved when course is archived', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = Course::factory()->published()->create(['visibility' => 'public']);
            $learner = User::factory()->create(['role' => 'learner']);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'progress_percentage' => 50,
            ]);

            // Archive the course
            $this->actingAs($admin)
                ->post(route('courses.archive', $course));

            // Enrollment should still exist with original data
            $enrollment->refresh();
            expect($enrollment)
                ->isActive()->toBeTrue()
                ->progress_percentage->toBe(50);
        });

        it('enrolled learner can still view archived course lessons', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $course = createPublishedCourseWithContent();
            $learner = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Archive the course
            $this->actingAs($admin)
                ->post(route('courses.archive', $course));

            $lesson = $course->lessons->first();

            // Should still be able to view lesson
            $this->actingAs($learner)
                ->get(route('courses.lessons.show', [$course, $lesson]))
                ->assertOk();
        });

    });

    describe('Draft Assessment Restrictions', function () {

        it('cannot start draft assessment', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'status' => 'draft',
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $this->actingAs($learner)
                ->post(route('assessments.start', [$course, $assessment]))
                ->assertForbidden();
        });

        it('learner cannot view draft assessment details', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'status' => 'draft',
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $this->actingAs($learner)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertForbidden();
        });

        it('course owner can view their draft assessment', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'status' => 'draft',
            ]);

            $this->actingAs($cm)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertOk();
        });

    });

    describe('Archived Assessment Restrictions', function () {

        it('cannot start archived assessment', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'status' => 'archived',
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $this->actingAs($learner)
                ->post(route('assessments.start', [$course, $assessment]))
                ->assertForbidden();
        });

        it('existing attempts preserved when assessment is archived', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
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
                'score' => 85,
            ]);

            // Archive the assessment (via direct update since no dedicated route)
            $assessment->update(['status' => 'archived']);

            // Attempt data should be preserved
            $attempt->refresh();
            expect($attempt)->score->toBe(85);
        });

    });

    describe('Enrollment Status Restrictions', function () {

        it('dropped enrollment cannot view lessons', function () {
            $course = createPublishedCourseWithContent();
            $learner = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->dropped()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $lesson = $course->lessons->first();

            $this->actingAs($learner)
                ->get(route('courses.lessons.show', [$course, $lesson]))
                ->assertForbidden();
        });

        it('dropped enrollment cannot update progress', function () {
            $course = createPublishedCourseWithContent();
            $learner = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->dropped()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $lesson = $course->lessons->first();

            $this->actingAs($learner)
                ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                    'current_page' => 1,
                    'total_pages' => 1,
                ])
                ->assertForbidden();
        });

        it('dropped enrollment cannot start assessment', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Enrollment::factory()->dropped()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $this->actingAs($learner)
                ->post(route('assessments.start', [$course, $assessment]))
                ->assertForbidden();
        });

        it('completed enrollment can still view lessons', function () {
            $course = createPublishedCourseWithContent();
            $learner = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $lesson = $course->lessons->first();

            $this->actingAs($learner)
                ->get(route('courses.lessons.show', [$course, $lesson]))
                ->assertOk();
        });

        it('completed enrollment can retake assessments if attempts remain', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'max_attempts' => 3, // Multiple attempts allowed
            ]);

            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Create one completed attempt
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'attempt_number' => 1,
            ]);

            // Should be able to start another attempt
            $this->actingAs($learner)
                ->post(route('assessments.start', [$course, $assessment]))
                ->assertRedirect(); // Successful redirect to attempt page
        });

    });

    describe('Attempt Status Restrictions', function () {

        it('cannot submit already submitted attempt', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);
            $question = Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_type' => 'true_false',
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            $this->actingAs($learner)
                ->post(route('assessments.attempt.submit', [$course, $assessment, $attempt]), [
                    'answers' => [
                        ['question_id' => $question->id, 'answer_text' => 'true'],
                    ],
                ])
                ->assertForbidden();
        });

        it('cannot submit graded attempt', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);
            $question = Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_type' => 'true_false',
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            $this->actingAs($learner)
                ->post(route('assessments.attempt.submit', [$course, $assessment, $attempt]), [
                    'answers' => [
                        ['question_id' => $question->id, 'answer_text' => 'true'],
                    ],
                ])
                ->assertForbidden();
        });

        it('cannot grade in-progress attempt', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // Course owner tries to grade in-progress attempt
            $this->actingAs($cm)
                ->get(route('assessments.grade', [$course, $assessment, $attempt]))
                ->assertForbidden();
        });

        it('learner can view their graded attempt results', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
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

            $this->actingAs($learner)
                ->get(route('assessments.attempt', [$course, $assessment, $attempt]))
                ->assertOk();
        });

    });

    describe('Published Course Content Modification Restrictions', function () {

        it('content manager cannot add section to published course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), ['title' => 'New Section'])
                ->assertForbidden();
        });

        it('content manager cannot add lesson to published course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'New Lesson',
                    'content_type' => 'text',
                ])
                ->assertForbidden();
        });

        it('content manager cannot delete section from published course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($cm)
                ->delete(route('sections.destroy', $section))
                ->assertForbidden();

            $this->assertDatabaseHas('course_sections', ['id' => $section->id]);
        });

        it('content manager cannot delete lesson from published course', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $this->actingAs($cm)
                ->delete(route('lessons.destroy', $lesson))
                ->assertForbidden();

            $this->assertDatabaseHas('lessons', ['id' => $lesson->id]);
        });

        it('admin can add section to published course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->post(route('courses.sections.store', $course), ['title' => 'Admin Section'])
                ->assertRedirect();

            $this->assertDatabaseHas('course_sections', [
                'course_id' => $course->id,
                'title' => 'Admin Section',
            ]);
        });

        it('admin can add lesson to published course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($admin)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Admin Lesson',
                    'content_type' => 'text',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('lessons', ['title' => 'Admin Lesson']);
        });

    });

});
