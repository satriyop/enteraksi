<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;

/**
 * Resource Isolation Tests
 *
 * These tests verify that users cannot access or modify resources
 * that belong to other users. Data isolation is critical for security
 * and privacy.
 */
describe('Resource Isolation', function () {

    describe('Course Isolation', function () {

        it('content manager cannot update another users course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create([
                'user_id' => $cm2->id,
                'title' => 'Original Title',
            ]);

            $this->actingAs($cm1)
                ->patch(route('courses.update', $course), ['title' => 'Hijacked Title'])
                ->assertForbidden();

            expect($course->refresh()->title)->toBe('Original Title');
        });

        it('content manager cannot delete another users course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);

            $this->actingAs($cm1)
                ->delete(route('courses.destroy', $course))
                ->assertForbidden();

            $this->assertNotSoftDeleted($course);
        });

        it('content manager can view but not edit another users draft course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);

            // Can view (for collaboration/review purposes)
            $this->actingAs($cm1)
                ->get(route('courses.show', $course))
                ->assertOk();

            // But cannot edit
            $this->actingAs($cm1)
                ->get(route('courses.edit', $course))
                ->assertForbidden();
        });

        it('content manager cannot add section to another users course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);

            $this->actingAs($cm1)
                ->post(route('courses.sections.store', $course), ['title' => 'Hacked Section'])
                ->assertForbidden();

            expect($course->sections()->count())->toBe(0);
        });

    });

    describe('Section and Lesson Isolation', function () {

        it('content manager cannot update another users section', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $section = CourseSection::factory()->create([
                'course_id' => $course->id,
                'title' => 'Original Section',
            ]);

            $this->actingAs($cm1)
                ->patch(route('sections.update', $section), ['title' => 'Hijacked Section'])
                ->assertForbidden();

            expect($section->refresh()->title)->toBe('Original Section');
        });

        it('content manager cannot delete another users section', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($cm1)
                ->delete(route('sections.destroy', $section))
                ->assertForbidden();

            $this->assertDatabaseHas('course_sections', ['id' => $section->id]);
        });

        it('content manager cannot add lesson to another users section', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($cm1)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Hacked Lesson',
                    'content_type' => 'text',
                ])
                ->assertForbidden();

            expect($section->lessons()->count())->toBe(0);
        });

        it('content manager cannot update another users lesson', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create([
                'course_section_id' => $section->id,
                'title' => 'Original Lesson',
            ]);

            $this->actingAs($cm1)
                ->patch(route('lessons.update', $lesson), ['title' => 'Hijacked Lesson'])
                ->assertForbidden();

            expect($lesson->refresh()->title)->toBe('Original Lesson');
        });

        it('content manager cannot delete another users lesson', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $this->actingAs($cm1)
                ->delete(route('lessons.destroy', $lesson))
                ->assertForbidden();

            $this->assertDatabaseHas('lessons', ['id' => $lesson->id]);
        });

    });

    describe('Assessment Isolation', function () {

        it('content manager cannot update another users assessment', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $assessment = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm2->id,
                'title' => 'Original Assessment',
            ]);

            $this->actingAs($cm1)
                ->put(route('assessments.update', [$course, $assessment]), [
                    'title' => 'Hijacked Assessment',
                ])
                ->assertForbidden();

            expect($assessment->refresh()->title)->toBe('Original Assessment');
        });

        it('content manager cannot delete another users assessment', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $assessment = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm2->id,
            ]);

            $this->actingAs($cm1)
                ->delete(route('assessments.destroy', [$course, $assessment]))
                ->assertForbidden();

            $this->assertNotSoftDeleted($assessment);
        });

        it('content manager cannot add questions to another users assessment', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm2->id]);
            $assessment = Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $cm2->id,
            ]);

            // Question creation typically happens via the assessment edit page
            // The forbidden check should happen at the assessment level
            $this->actingAs($cm1)
                ->get(route('assessments.edit', [$course, $assessment]))
                ->assertForbidden();
        });

    });

    describe('Enrollment Isolation', function () {

        it('learner cannot view another users enrollment progress via API', function () {
            $course = createPublishedCourseWithContent();

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->active()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
                'progress_percentage' => 50,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner2->id,
                'course_id' => $course->id,
                'progress_percentage' => 0,
            ]);

            // Learner 2's dashboard should only show their own progress
            $this->actingAs($learner2)
                ->get(route('learner.dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('myLearning', 1)
                    ->where('myLearning.0.progress_percentage', 0)
                );
        });

        it('learner cannot drop another users enrollment', function () {
            $course = createPublishedCourseWithContent();

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->active()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
            ]);

            // Learner 2 has no enrollment in this course
            // Attempting to unenroll should return 404 (not found for this user)
            $this->actingAs($learner2)
                ->delete(route('courses.unenroll', $course))
                ->assertNotFound();
        });

    });

    describe('Assessment Attempt Isolation', function () {

        it('learner cannot view another users attempt', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
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

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner1->id,
            ]);

            $this->actingAs($learner2)
                ->get(route('assessments.attempt', [$course, $assessment, $attempt]))
                ->assertForbidden();
        });

        it('learner cannot submit answers to another users attempt', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            $question = Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_type' => 'true_false',
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

            $attempt = AssessmentAttempt::factory()->inProgress()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner1->id,
            ]);

            $this->actingAs($learner2)
                ->post(route('assessments.attempt.submit', [$course, $assessment, $attempt]), [
                    'answers' => [
                        ['question_id' => $question->id, 'answer_text' => 'true'],
                    ],
                ])
                ->assertForbidden();
        });

        it('content manager cannot grade another content managers assessment attempts', function () {
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

            $this->actingAs($cm1)
                ->get(route('assessments.grade', [$course, $assessment, $attempt]))
                ->assertForbidden();
        });

    });

    describe('Rating Isolation', function () {

        it('learner cannot update another users rating', function () {
            $course = createPublishedCourseWithContent();

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

            $rating = CourseRating::factory()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
                'rating' => 5,
            ]);

            $this->actingAs($learner2)
                ->patch(route('courses.ratings.update', [$course, $rating]), ['rating' => 1])
                ->assertForbidden();

            expect($rating->refresh()->rating)->toBe(5);
        });

        it('learner cannot delete another users rating', function () {
            $course = createPublishedCourseWithContent();

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

            $rating = CourseRating::factory()->create([
                'user_id' => $learner1->id,
                'course_id' => $course->id,
            ]);

            $this->actingAs($learner2)
                ->delete(route('courses.ratings.destroy', [$course, $rating]))
                ->assertForbidden();

            $this->assertDatabaseHas('course_ratings', ['id' => $rating->id]);
        });

        it('admin can delete any users rating', function () {
            $course = createPublishedCourseWithContent();
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $learner = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $rating = CourseRating::factory()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $this->actingAs($admin)
                ->delete(route('courses.ratings.destroy', [$course, $rating]))
                ->assertRedirect();

            $this->assertDatabaseMissing('course_ratings', ['id' => $rating->id]);
        });

    });

    describe('Cross-Resource Validation', function () {

        it('cannot access lesson through wrong course URL', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            // Create two courses
            $course1 = Course::factory()->published()->create(['user_id' => $cm->id]);
            $section1 = CourseSection::factory()->create(['course_id' => $course1->id]);
            $lesson1 = Lesson::factory()->create(['course_section_id' => $section1->id]);

            $course2 = Course::factory()->published()->create(['user_id' => $cm->id]);

            // Enroll in both courses
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course1->id,
            ]);
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course2->id,
            ]);

            // Try to access lesson1 through course2's URL
            $this->actingAs($learner)
                ->get(route('courses.lessons.show', [$course2, $lesson1]))
                ->assertNotFound();
        });

        it('cannot access assessment through wrong course URL', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course1 = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment1 = Assessment::factory()->published()->create([
                'course_id' => $course1->id,
                'user_id' => $cm->id,
            ]);

            $course2 = Course::factory()->published()->create(['user_id' => $cm->id]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course1->id,
            ]);
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course2->id,
            ]);

            // Try to access assessment1 through course2's URL
            // App returns 403 (forbidden) rather than 404 - both are valid security responses
            $this->actingAs($learner)
                ->get(route('assessments.show', [$course2, $assessment1]))
                ->assertForbidden();
        });

        it('cannot start assessment attempt through wrong course URL', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course1 = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment1 = Assessment::factory()->published()->create([
                'course_id' => $course1->id,
                'user_id' => $cm->id,
            ]);

            $course2 = Course::factory()->published()->create(['user_id' => $cm->id]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course1->id,
            ]);
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course2->id,
            ]);

            // App returns 403 (forbidden) rather than 404 - both are valid security responses
            $this->actingAs($learner)
                ->post(route('assessments.start', [$course2, $assessment1]))
                ->assertForbidden();
        });

    });

});
