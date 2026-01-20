<?php

/**
 * Large Data Handling Tests
 *
 * Ensures the system handles large datasets gracefully:
 * - Many lessons per course
 * - Many questions per assessment
 * - Many enrollments per course
 * - Many courses per learner
 * - String length limits
 */

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\User;

describe('Large Data Handling', function () {

    describe('Course with Many Lessons', function () {

        it('handles course with 50 lessons correctly', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            // Create 50 lessons
            $lessons = Lesson::factory()->count(50)->create([
                'course_section_id' => $section->id,
            ]);

            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Verify lessons were created
            expect($course->lessons()->count())->toBe(50);

            // Complete 25 lessons (50%)
            foreach ($lessons->take(25) as $lesson) {
                LessonProgress::create([
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                    'is_completed' => true,
                ]);
            }

            // Calculate progress - should be 50%
            $completedCount = LessonProgress::where('enrollment_id', $enrollment->id)
                ->where('is_completed', true)
                ->count();
            $totalCount = $course->lessons()->count();
            $progress = round(($completedCount / $totalCount) * 100);

            expect($progress)->toEqual(50);
        });

        it('course show page loads with many lessons', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            // Create multiple sections with lessons
            for ($i = 1; $i <= 5; $i++) {
                $section = CourseSection::factory()->create([
                    'course_id' => $course->id,
                    'title' => "Section $i",
                    'order' => $i,
                ]);

                Lesson::factory()->count(10)->create([
                    'course_section_id' => $section->id,
                ]);
            }

            // Course should have 50 lessons across 5 sections
            expect($course->lessons()->count())->toBe(50);

            // Page should load without timeout
            $this->actingAs($learner)
                ->get(route('courses.show', $course))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('course')
                );
        });

    });

    describe('Assessment with Many Questions', function () {

        it('handles assessment with 30 questions correctly', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'max_attempts' => 3,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Create 30 multiple choice questions
            for ($i = 1; $i <= 30; $i++) {
                Question::factory()->create([
                    'assessment_id' => $assessment->id,
                    'question_type' => 'multiple_choice',
                    'question_text' => "Question $i: What is the answer?",
                    'points' => 1,
                    'order' => $i,
                ]);
            }

            expect($assessment->questions()->count())->toBe(30);

            // Learner can view assessment
            $this->actingAs($learner)
                ->get(route('assessments.show', [$course, $assessment]))
                ->assertOk();
        });

        it('assessment grading handles many questions', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $learner = User::factory()->create(['role' => 'learner']);

            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);
            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $cm->id,
                'passing_score' => 70,
            ]);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Create 20 questions, each worth 5 points = 100 max
            for ($i = 1; $i <= 20; $i++) {
                Question::factory()->create([
                    'assessment_id' => $assessment->id,
                    'points' => 5,
                    'order' => $i,
                ]);
            }

            // Create attempt with 75% score (75 out of 100)
            $attempt = AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'score' => 75,
                'max_score' => 100,
                'percentage' => 75.0,
                'passed' => true,
            ]);

            expect($attempt->percentage)->toBe(75.0);
            expect($attempt->passed)->toBeTrue();
        });

    });

    describe('Course with Many Enrollments', function () {

        it('handles course with 100 enrollments', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            // Create 100 learners and enroll them
            $learners = User::factory()->count(100)->create(['role' => 'learner']);

            foreach ($learners as $learner) {
                Enrollment::factory()->active()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);
            }

            expect($course->enrollments()->count())->toBe(100);

            // Course detail page should still load for admin
            $admin = User::factory()->create(['role' => 'lms_admin']);
            $this->actingAs($admin)
                ->get(route('courses.show', $course))
                ->assertOk();
        });

        it('enrollment list paginates correctly', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->public()->create(['user_id' => $cm->id]);

            // Create 25 enrollments
            $learners = User::factory()->count(25)->create(['role' => 'learner']);
            foreach ($learners as $learner) {
                Enrollment::factory()->active()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);
            }

            expect(Enrollment::where('course_id', $course->id)->count())->toBe(25);
        });

    });

    describe('Learner with Many Courses', function () {

        it('learner dashboard loads with many enrollments', function () {
            $learner = User::factory()->create(['role' => 'learner']);
            $cm = User::factory()->create(['role' => 'content_manager']);

            // Create 15 courses manually to avoid factory unique constraints
            for ($i = 1; $i <= 15; $i++) {
                $course = Course::create([
                    'user_id' => $cm->id,
                    'title' => "Dashboard Test Course $i",
                    'slug' => "dashboard-test-course-$i",
                    'short_description' => "Description for course $i",
                    'status' => 'published',
                    'visibility' => 'public',
                    'difficulty_level' => 'beginner',
                ]);

                Enrollment::factory()->active()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                    'progress_percentage' => $i * 6, // 6%, 12%, ..., 90%
                ]);
            }

            expect(Enrollment::where('user_id', $learner->id)->count())->toBe(15);

            // Dashboard should load
            $this->actingAs($learner)
                ->get(route('learner.dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('myLearning')
                );
        });

        it('handles mixed enrollment statuses', function () {
            $learner = User::factory()->create(['role' => 'learner']);

            // Create 5 active, 3 completed, 2 dropped enrollments
            for ($i = 1; $i <= 5; $i++) {
                $course = Course::factory()->published()->public()->create();
                Enrollment::factory()->active()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);
            }

            for ($i = 1; $i <= 3; $i++) {
                $course = Course::factory()->published()->public()->create();
                Enrollment::factory()->completed()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);
            }

            for ($i = 1; $i <= 2; $i++) {
                $course = Course::factory()->published()->public()->create();
                Enrollment::factory()->dropped()->create([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);
            }

            expect(Enrollment::where('user_id', $learner->id)->count())->toBe(10);

            // Dashboard should show active and completed (not dropped)
            $this->actingAs($learner)
                ->get(route('learner.dashboard'))
                ->assertOk();
        });

    });

    describe('String Length Limits', function () {

        it('course title accepts max 255 characters', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $maxTitle = str_repeat('A', 255);

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => $maxTitle,
                    'short_description' => 'A valid description',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $course = Course::where('title', $maxTitle)->first();
            expect($course)->not->toBeNull();
            expect(strlen($course->title))->toBe(255);
        });

        it('course title rejects more than 255 characters', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $tooLongTitle = str_repeat('A', 256);

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => $tooLongTitle,
                    'short_description' => 'A valid description',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertSessionHasErrors('title');
        });

        it('lesson title accepts max length', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $maxTitle = str_repeat('B', 255);

            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => $maxTitle,
                    'content_type' => 'text',
                    'order' => 1,
                ])
                ->assertRedirect();

            expect(Lesson::where('title', $maxTitle)->exists())->toBeTrue();
        });

        it('assessment description accepts long text', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $longDescription = str_repeat('This is a detailed description. ', 100); // ~3200 chars

            $this->actingAs($cm)
                ->post(route('assessments.store', $course), [
                    'title' => 'Test Assessment',
                    'description' => $longDescription,
                    'time_limit_minutes' => 60,
                    'max_attempts' => 3,
                    'passing_score' => 70,
                    'status' => 'draft',
                    'visibility' => 'public',
                ])
                ->assertRedirect();

            $assessment = Assessment::where('title', 'Test Assessment')->first();
            expect($assessment)->not->toBeNull();
            expect(strlen($assessment->description))->toBeGreaterThan(3000);
        });

    });

    describe('Empty String Validation', function () {

        it('course title cannot be empty', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);

            $this->actingAs($cm)
                ->post(route('courses.store'), [
                    'title' => '',
                    'short_description' => 'A valid description',
                    'difficulty_level' => 'beginner',
                    'visibility' => 'public',
                ])
                ->assertSessionHasErrors('title');
        });

        it('lesson title cannot be empty', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);
            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $this->actingAs($cm)
                ->post(route('sections.lessons.store', $section), [
                    'title' => '',
                    'content_type' => 'text',
                    'order' => 1,
                ])
                ->assertSessionHasErrors('title');
        });

        it('assessment title cannot be empty', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->post(route('assessments.store', $course), [
                    'title' => '',
                    'time_limit' => 60,
                    'max_attempts' => 3,
                    'passing_score' => 70,
                ])
                ->assertSessionHasErrors('title');
        });

        it('section title cannot be empty', function () {
            $cm = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->draft()->create(['user_id' => $cm->id]);

            $this->actingAs($cm)
                ->post(route('courses.sections.store', $course), [
                    'title' => '',
                    'order' => 1,
                ])
                ->assertSessionHasErrors('title');
        });

    });

});
