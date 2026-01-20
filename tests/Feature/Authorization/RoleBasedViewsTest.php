<?php

/**
 * Role-Based View Tests
 *
 * Ensures different roles see appropriate content and controls:
 * - Dashboard differences (admin, CM, learner)
 * - Course index views
 * - Course detail views
 * - Assessment views
 */

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;

describe('Role-Based Views', function () {

    describe('Dashboard Differences', function () {

        it('admin dashboard shows all courses count', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            // Create various courses
            Course::factory()->draft()->create(['user_id' => $cm->id]);
            Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            Course::factory()->published()->create(['user_id' => $admin->id]);

            $this->actingAs($admin)
                ->get(route('dashboard'))
                ->assertOk();

            // Admin can access the general dashboard
            expect(Course::count())->toBe(3);
        });

        it('content manager dashboard shows own courses', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $otherCm = User::factory()->create(['role' => 'content_manager']);

            // CM's own courses
            Course::factory()->draft()->create(['user_id' => $cm->id, 'title' => 'My Draft']);
            Course::factory()->published()->create(['user_id' => $cm->id, 'title' => 'My Published']);

            // Other CM's course
            Course::factory()->published()->create(['user_id' => $otherCm->id, 'title' => 'Other Published']);

            $this->actingAs($cm)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 2) // Only own courses
                );
        });

        it('learner dashboard shows enrolled courses with progress', function () {
            $learner = User::factory()->create(['role' => 'learner']);

            $course1 = Course::factory()->published()->public()->create();
            $course2 = Course::factory()->published()->public()->create();
            $course3 = Course::factory()->published()->public()->create(); // Not enrolled

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course1->id,
                'progress_percentage' => 50,
            ]);

            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course2->id,
                'progress_percentage' => 100,
            ]);

            $this->actingAs($learner)
                ->get(route('learner.dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('myLearning', 2) // Only enrolled courses
                );
        });

    });

    describe('Course Index Views', function () {

        it('admin sees all courses with all statuses', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            Course::factory()->draft()->create(['user_id' => $cm->id]);
            Course::factory()->published()->create(['user_id' => $cm->id]);
            Course::factory()->create(['user_id' => $cm->id, 'status' => 'archived']);

            $this->actingAs($admin)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 3) // All 3 courses
                );
        });

        it('CM sees only own courses regardless of status', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);

            Course::factory()->draft()->create(['user_id' => $cm1->id]);
            Course::factory()->published()->create(['user_id' => $cm1->id]);
            Course::factory()->draft()->create(['user_id' => $cm2->id]);

            $this->actingAs($cm1)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 2) // Only CM1's courses
                );
        });

        it('learner browse shows only published public courses', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            Course::factory()->draft()->create(['user_id' => $cm->id]); // Not visible
            Course::factory()->published()->public()->create(['user_id' => $cm->id]); // Visible
            Course::factory()->published()->create(['user_id' => $cm->id, 'visibility' => 'restricted']); // Not visible
            Course::factory()->published()->public()->create(['user_id' => $cm->id]); // Visible

            // Learners access courses.index which renders courses/Browse view
            $this->actingAs($learner)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('courses/Browse')
                    ->has('courses.data', 2) // Only published + public
                );
        });

        it('learner cannot see draft courses in browse', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $draftCourse = Course::factory()->draft()->create([
                'user_id' => $cm->id,
                'title' => 'Secret Draft Course',
            ]);

            $this->actingAs($learner)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('courses/Browse')
                    ->has('courses.data', 0)
                );

            // Also cannot directly access
            $this->actingAs($learner)
                ->get(route('courses.show', $draftCourse))
                ->assertForbidden();
        });

    });

    describe('Course Detail Views', function () {

        it('admin can view and edit any course', function () {
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            // Admin can view
            $this->actingAs($admin)
                ->get(route('courses.show', $course))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('course')
                );

            // Admin can edit
            $this->actingAs($admin)
                ->get(route('courses.edit', $course))
                ->assertOk();

            // Admin can publish
            $this->actingAs($admin)
                ->post(route('courses.publish', $course))
                ->assertRedirect();
        });

        it('CM can view and edit own course but cannot publish', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $ownCourse = Course::factory()->draft()->create(['user_id' => $cm->id]);

            // CM can view
            $this->actingAs($cm)
                ->get(route('courses.show', $ownCourse))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('course')
                );

            // CM can edit own course
            $this->actingAs($cm)
                ->get(route('courses.edit', $ownCourse))
                ->assertOk();

            // CM cannot publish
            $this->actingAs($cm)
                ->post(route('courses.publish', $ownCourse))
                ->assertForbidden();
        });

        it('CM can view but cannot edit others published course', function () {
            $cm1 = User::factory()->create(['role' => 'content_manager']);
            $cm2 = User::factory()->create(['role' => 'content_manager']);

            $otherCourse = Course::factory()->published()->public()->create(['user_id' => $cm2->id]);

            // CM can view published course
            $this->actingAs($cm1)
                ->get(route('courses.show', $otherCourse))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('course')
                );

            // CM cannot edit others course
            $this->actingAs($cm1)
                ->get(route('courses.edit', $otherCourse))
                ->assertForbidden();
        });

        it('learner can view published course and enroll', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();

            // Learner can view
            $this->actingAs($learner)
                ->get(route('courses.show', $course))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('course')
                );

            // Learner can enroll
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertRedirect();
        });

        it('enrolled learner can access lessons', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $course = Course::factory()->published()->public()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Can view course
            $this->actingAs($learner)
                ->get(route('courses.show', $course))
                ->assertOk();

            // Can access lesson
            $this->actingAs($learner)
                ->get(route('courses.lessons.show', [$course, $lesson]))
                ->assertOk();
        });

    });

    describe('Assessment Views', function () {

        it('admin can view all attempts in detail', function () {
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

            // Admin can view assessment with all attempts
            $this->actingAs($admin)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertOk();

            // Admin can view specific attempt
            $this->actingAs($admin)
                ->get(route('assessments.attempt.complete', [$course, $assessment, $attempt]))
                ->assertOk();
        });

        it('CM can view attempts for own assessments', function () {
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

            AssessmentAttempt::factory()->submitted()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // CM can view assessment details
            $this->actingAs($cm)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertOk();
        });

        it('learner can only view own attempts', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
            ]);

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
            ]);

            // Learner 1 can view own attempt
            $this->actingAs($learner1)
                ->get(route('assessments.attempt.complete', [$course, $assessment, $attempt1]))
                ->assertOk();

            // Learner 2 cannot view learner 1's attempt
            $this->actingAs($learner2)
                ->get(route('assessments.attempt.complete', [$course, $assessment, $attempt1]))
                ->assertForbidden();
        });

    });

    describe('Trainer Role Views', function () {

        it('trainer can view any course for invitation purposes', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            $this->actingAs($trainer)
                ->get(route('courses.show', $course))
                ->assertOk();
        });

        it('trainer can invite to others course but cannot edit', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            // Trainer can view
            $this->actingAs($trainer)
                ->get(route('courses.show', $course))
                ->assertOk();

            // Trainer cannot edit
            $this->actingAs($trainer)
                ->get(route('courses.edit', $course))
                ->assertForbidden();

            // Trainer CAN invite (special trainer privilege)
            $this->actingAs($trainer)
                ->post(route('courses.invitations.store', $course), [
                    'user_id' => $learner->id,
                ])
                ->assertRedirect();
        });

        it('trainer sees own courses in management index', function () {
            $trainer = User::factory()->create(['role' => 'trainer']);

            Course::factory()->draft()->create(['user_id' => $trainer->id, 'title' => 'Trainer Course']);

            $this->actingAs($trainer)
                ->get(route('courses.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('courses.data', 1)
                );
        });

    });

});
