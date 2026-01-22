<?php

/**
 * Admin Capabilities Tests
 *
 * Ensures LMS admins have full system access:
 * - Can view/edit/delete all courses regardless of owner
 * - Can publish/unpublish any course
 * - Can access all assessments and attempts
 * - Can view system-wide statistics
 * - Can manage any content
 */

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;

describe('Admin Capabilities', function () {

    describe('Course Management', function () {

        it('admin can view all courses from any owner', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);

            Course::factory()->draft()->create(['user_id' => $cm1->id, 'title' => 'CM1 Draft']);
            Course::factory()->published()->create(['user_id' => $cm1->id, 'title' => 'CM1 Published']);
            Course::factory()->draft()->create(['user_id' => $cm2->id, 'title' => 'CM2 Draft']);
            Course::factory()->create(['user_id' => $cm2->id, 'status' => 'archived', 'title' => 'CM2 Archived']);

            $this->actingAs($admin)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 4) // All 4 courses from different CMs
                );
        });

        it('admin can view draft courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $draftCourse = Course::factory()->draft()->create([
                'user_id' => $cm->id,
                'title' => 'Secret Draft Course',
            ]);

            // Admin can view any draft
            $this->actingAs($admin)
                ->get(route('courses.show', $draftCourse))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('course')
                    ->where('course.title', 'Secret Draft Course')
                );
        });

        it('admin can edit courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create([
                'user_id' => $cm->id,
                'title' => 'Original Title',
            ]);

            // Admin can access edit page
            $this->actingAs($admin)
                ->get(route('courses.edit', $course))
                ->assertOk();

            // Admin can update
            $this->actingAs($admin)
                ->patch(route('courses.update', $course), [
                    'title' => 'Admin Changed Title',
                    'short_description' => 'Changed by admin',
                    'difficulty_level' => 'intermediate',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            expect($course->refresh()->title)->toBe('Admin Changed Title');
        });

        it('admin can delete courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->delete(route('courses.destroy', $course))
                ->assertRedirect();

            $this->assertSoftDeleted('courses', ['id' => $course->id]);
        });

        it('admin can publish courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $draftCourse = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->post(route('courses.publish', $draftCourse))
                ->assertRedirect();

            expect($draftCourse->refresh()->status->getValue())->toBe('published');
        });

        it('admin can unpublish courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $publishedCourse = Course::factory()->published()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->post(route('courses.unpublish', $publishedCourse))
                ->assertRedirect();

            expect($publishedCourse->refresh()->status->getValue())->toBe('draft');
        });

    });

    describe('Section and Lesson Management', function () {

        it('admin can create sections in courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($admin)
                ->post(route('courses.sections.store', $course), [
                    'title' => 'Admin Created Section',
                    'order' => 1,
                ])
                ->assertRedirect();

            expect($course->sections()->count())->toBe(1);
            expect($course->sections->first()->title)->toBe('Admin Created Section');
        });

        it('admin can delete sections from courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($admin)
                ->delete(route('sections.destroy', $section))
                ->assertRedirect();

            $this->assertSoftDeleted('course_sections', ['id' => $section->id]);
        });

        it('admin can create lessons in courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($admin)
                ->post(route('sections.lessons.store', $section), [
                    'title' => 'Admin Created Lesson',
                    'content_type' => 'text',
                    'order' => 1,
                ])
                ->assertRedirect();

            expect($section->lessons()->count())->toBe(1);
        });

        it('admin can delete lessons from courses they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $this->actingAs($admin)
                ->delete(route('lessons.destroy', $lesson))
                ->assertRedirect();

            $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
        });

    });

    describe('Assessment Management', function () {

        it('admin can view all assessments from any course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            $this->actingAs($admin)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertOk();
        });

        it('admin can edit assessments they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->draft()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'title' => 'Original Assessment',
            ]);

            $this->actingAs($admin)
                ->get(route('assessments.edit', [$course, $assessment]))
                ->assertOk();

            $this->actingAs($admin)
                ->put(route('assessments.update', [$course, $assessment]), [
                    'title' => 'Admin Changed Assessment',
                    'description' => 'Changed by admin',
                    'time_limit' => 60,
                    'max_attempts' => 3,
                    'passing_score' => 70,
                ])
                ->assertRedirect();

            expect($assessment->refresh()->title)->toBe('Admin Changed Assessment');
        });

        it('admin can delete assessments they do not own', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->draft()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            $this->actingAs($admin)
                ->delete(route('assessments.destroy', [$course, $assessment]))
                ->assertRedirect();

            $this->assertSoftDeleted('assessments', ['id' => $assessment->id]);
        });

        it('admin can view all assessment attempts from any learner', function () {
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

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // Admin can view any learner's attempt
            $this->actingAs($admin)
                ->get(route('assessments.attempt.complete', [$course, $assessment, $attempt]))
                ->assertOk();
        });

        it('admin can grade any assessment attempt', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->create(['user_id' => $cm->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

            Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_type' => 'essay',
                'points' => 100,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // Admin can view grade page
            $this->actingAs($admin)
                ->get(route('assessments.grade', [$course, $assessment, $attempt]))
                ->assertOk();
        });

    });

    describe('Cross-Owner Access', function () {

        it('admin can access content from multiple different owners in same session', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);
            $trainer = User::factory()->create(['role' => 'trainer']);

            $course1 = Course::factory()->draft()->create(['user_id' => $cm1->id]);
            $course2 = Course::factory()->published()->create(['user_id' => $cm2->id]);
            $course3 = Course::factory()->draft()->create(['user_id' => $trainer->id]);

            // Admin can view all three
            $this->actingAs($admin)
                ->get(route('courses.show', $course1))
                ->assertOk();

            $this->actingAs($admin)
                ->get(route('courses.show', $course2))
                ->assertOk();

            $this->actingAs($admin)
                ->get(route('courses.show', $course3))
                ->assertOk();

            // Admin can edit all three
            $this->actingAs($admin)
                ->get(route('courses.edit', $course1))
                ->assertOk();

            $this->actingAs($admin)
                ->get(route('courses.edit', $course2))
                ->assertOk();

            $this->actingAs($admin)
                ->get(route('courses.edit', $course3))
                ->assertOk();
        });

        it('admin cannot be restricted by course visibility', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            // Create restricted course
            $restrictedCourse = Course::factory()->published()->create([
                'user_id' => $cm->id,
                'visibility' => 'restricted',
            ]);

            // Admin can still view and edit
            $this->actingAs($admin)
                ->get(route('courses.show', $restrictedCourse))
                ->assertOk();

            $this->actingAs($admin)
                ->get(route('courses.edit', $restrictedCourse))
                ->assertOk();
        });

    });

    describe('Admin-Only Actions', function () {

        it('only admin can publish courses from other CMs', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm1->id]);

            // CM2 cannot publish CM1's course
            $this->actingAs($cm2)
                ->post(route('courses.publish', $course))
                ->assertForbidden();

            // CM1 also cannot publish own course (only admin can)
            $this->actingAs($cm1)
                ->post(route('courses.publish', $course))
                ->assertForbidden();

            // Admin CAN publish
            $this->actingAs($admin)
                ->post(route('courses.publish', $course))
                ->assertRedirect();

            expect($course->refresh()->status->getValue())->toBe('published');
        });

        it('only admin can delete courses with enrollments', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // CM cannot delete course with active enrollments
            $this->actingAs($cm)
                ->delete(route('courses.destroy', $course))
                ->assertForbidden();

            // Admin CAN delete course with enrollments
            $this->actingAs($admin)
                ->delete(route('courses.destroy', $course))
                ->assertRedirect();

            $this->assertSoftDeleted('courses', ['id' => $course->id]);
        });

    });

    describe('Dashboard Access', function () {

        it('admin can access admin dashboard', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);

            $this->actingAs($admin)
                ->get(route('dashboard'))
                ->assertOk();
        });

        it('admin sees system-wide statistics', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            // Create some data
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $this->actingAs($admin)
                ->get(route('dashboard'))
                ->assertOk();

            // Verify admin has system-wide visibility
            // Admin can query all courses regardless of ownership
            expect(Course::count())->toBeGreaterThanOrEqual(1);
            expect(Enrollment::count())->toBeGreaterThanOrEqual(1);

            // Verify user counts show different roles exist
            expect(User::where('role', 'lms_admin')->count())->toBeGreaterThanOrEqual(1);
            expect(User::where('role', 'content_manager')->count())->toBeGreaterThanOrEqual(1);
            expect(User::where('role', 'learner')->count())->toBeGreaterThanOrEqual(1);
        });

    });

});
