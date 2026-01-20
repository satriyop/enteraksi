<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;

/**
 * End-to-End Learner Journey Tests
 *
 * These tests validate complete user workflows from start to finish,
 * ensuring all components integrate correctly.
 *
 * Test Scenarios:
 * - Public course: browse → enroll → learn → complete
 * - Restricted course: invite → accept → learn → complete
 * - Course with assessment: learn → pass assessment → complete
 * - Course with rating: complete → rate → view dashboard
 */
describe('Learner Complete Journey', function () {

    describe('Public Course Journey', function () {

        it('completes full public course journey: browse → enroll → learn → complete', function () {
            // 1. Create published public course with content
            $course = createPublishedCourseWithContent(2, 2); // 2 sections, 2 lessons each = 4 lessons

            // 2. Create learner
            $learner = User::factory()->create(['role' => 'learner']);

            // 3. Learner can view course detail (browse)
            $this->actingAs($learner)
                ->get(route('courses.show', $course))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('courses/Detail')
                    ->has('course')
                );

            // 4. Learner enrolls
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertRedirect()
                ->assertSessionHas('success');

            // 5. Verify enrollment created with correct initial state
            $enrollment = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->first();

            expect($enrollment)->not->toBeNull();
            expect($enrollment->is_active)->toBeTrue();
            expect($enrollment->progress_percentage)->toBe(0);

            // 6. Learner views each lesson and completes them
            $lessons = $course->lessons;
            foreach ($lessons as $index => $lesson) {
                // View lesson
                $this->actingAs($learner)
                    ->get(route('courses.lessons.show', [$course, $lesson]))
                    ->assertOk();

                // Update progress (complete lesson)
                $this->actingAs($learner)
                    ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                        'current_page' => 1,
                        'total_pages' => 1,
                    ])
                    ->assertOk();
            }

            // 7. Verify enrollment is now completed
            $enrollment->refresh();

            expect($enrollment->is_completed)->toBeTrue();
            expect($enrollment->progress_percentage)->toBe(100);
            expect($enrollment->completed_at)->not->toBeNull();
        });

        it('maintains correct progress through partial completion', function () {
            $course = createPublishedCourseWithContent(1, 4); // 4 lessons
            $learner = User::factory()->create(['role' => 'learner']);

            // Enroll
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course));

            $enrollment = Enrollment::forUser($learner)->first();
            $lessons = $course->lessons;

            // Complete first 2 lessons (50%)
            foreach ($lessons->take(2) as $lesson) {
                $this->actingAs($learner)
                    ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                        'current_page' => 1,
                        'total_pages' => 1,
                    ]);
            }

            $enrollment->refresh();

            expect($enrollment->is_active)->toBeTrue();
            expect($enrollment->progress_percentage)->toBe(50);

            // Complete remaining 2 lessons
            foreach ($lessons->skip(2) as $lesson) {
                $this->actingAs($learner)
                    ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                        'current_page' => 1,
                        'total_pages' => 1,
                    ]);
            }

            $enrollment->refresh();

            expect($enrollment->is_completed)->toBeTrue();
            expect($enrollment->progress_percentage)->toBe(100);
        });

    });

    describe('Restricted Course Journey (Invitation Flow)', function () {

        it('completes restricted course journey via invitation', function () {
            // 1. Create restricted course
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create([
                'user_id' => $contentManager->id,
                'visibility' => 'restricted',
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            Lesson::factory()->count(2)->create(['course_section_id' => $section->id]);

            // 2. Learner cannot self-enroll in restricted course
            $learner = User::factory()->create(['role' => 'learner']);

            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertForbidden();

            // 3. Create invitation for learner
            $invitation = CourseInvitation::factory()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'status' => 'pending',
                'invited_by' => $contentManager->id,
            ]);

            // 4. Learner accepts invitation
            $this->actingAs($learner)
                ->post(route('invitations.accept', $invitation))
                ->assertRedirect();

            // 5. Verify enrollment created
            $enrollment = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->first();

            expect($enrollment)->not->toBeNull();
            expect($enrollment->is_active)->toBeTrue();

            // 6. Now learner can view lessons and complete course
            foreach ($course->lessons as $lesson) {
                $this->actingAs($learner)
                    ->get(route('courses.lessons.show', [$course, $lesson]))
                    ->assertOk();

                $this->actingAs($learner)
                    ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                        'current_page' => 1,
                        'total_pages' => 1,
                    ]);
            }

            $enrollment->refresh();
            expect($enrollment->is_completed)->toBeTrue();
        });

        it('prevents enrollment without invitation for restricted course', function () {
            $course = Course::factory()->published()->create(['visibility' => 'restricted']);
            $learner = User::factory()->create(['role' => 'learner']);

            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertForbidden();

            expect(Enrollment::where('user_id', $learner->id)->count())->toBe(0);
        });

    });

    describe('Course with Assessment Journey', function () {

        it('completes course with passing required assessment', function () {
            // 1. Create course with lesson and required assessment
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create([
                'user_id' => $contentManager->id,
                'visibility' => 'public',
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $contentManager->id,
                'passing_score' => 70,
                'max_attempts' => 3,
            ]);

            $question = Question::factory()->trueFalse()->create([
                'assessment_id' => $assessment->id,
                'points' => 100,
            ]);

            // 2. Learner enrolls
            $learner = User::factory()->create(['role' => 'learner']);

            $this->actingAs($learner)
                ->post(route('courses.enroll', $course));

            $enrollment = Enrollment::forUser($learner)->first();

            // 3. Complete lesson
            $this->actingAs($learner)
                ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                    'current_page' => 1,
                    'total_pages' => 1,
                ]);

            // 4. Start assessment attempt
            $this->actingAs($learner)
                ->post(route('assessments.start', [$course, $assessment]))
                ->assertRedirect();

            $attempt = AssessmentAttempt::where('user_id', $learner->id)->first();

            expect($attempt)
                ->not->toBeNull()
                ->status->toBe('in_progress');

            // 5. Submit correct answer
            $this->actingAs($learner)
                ->post(route('assessments.attempt.submit', [$course, $assessment, $attempt]), [
                    'answers' => [
                        [
                            'question_id' => $question->id,
                            'answer_text' => 'true',
                        ],
                    ],
                ])
                ->assertRedirect();

            // 6. Verify attempt is graded (auto-graded for true_false)
            $attempt->refresh();

            expect($attempt)
                ->status->toBe('graded')
                ->passed->toBeTrue()
                ->percentage->toBeGreaterThanOrEqual(70);
        });

        it('fails assessment and can retry within attempt limit', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create([
                'user_id' => $contentManager->id,
                'visibility' => 'public',
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $contentManager->id,
                'passing_score' => 70,
                'max_attempts' => 3,
            ]);

            $question = Question::factory()->trueFalse()->create([
                'assessment_id' => $assessment->id,
                'points' => 100,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // First attempt - wrong answer
            $this->actingAs($learner)
                ->post(route('assessments.start', [$course, $assessment]));

            $attempt1 = AssessmentAttempt::where('user_id', $learner->id)
                ->orderBy('id', 'desc')
                ->first();

            $this->actingAs($learner)
                ->post(route('assessments.attempt.submit', [$course, $assessment, $attempt1]), [
                    'answers' => [
                        ['question_id' => $question->id, 'answer_text' => 'false'],
                    ],
                ]);

            $attempt1->refresh();
            expect($attempt1)
                ->passed->toBeFalse()
                ->attempt_number->toBe(1);

            // Second attempt - correct answer
            $this->actingAs($learner)
                ->post(route('assessments.start', [$course, $assessment]));

            $attempt2 = AssessmentAttempt::where('user_id', $learner->id)
                ->orderBy('id', 'desc')
                ->first();

            $this->actingAs($learner)
                ->post(route('assessments.attempt.submit', [$course, $assessment, $attempt2]), [
                    'answers' => [
                        ['question_id' => $question->id, 'answer_text' => 'true'],
                    ],
                ]);

            $attempt2->refresh();
            expect($attempt2)
                ->passed->toBeTrue()
                ->attempt_number->toBe(2);
        });

    });

    describe('Course Completion with Rating', function () {

        it('completes course, rates it, and sees it on dashboard', function () {
            $course = createPublishedCourseWithContent(1, 2);
            $learner = User::factory()->create(['role' => 'learner']);

            // 1. Enroll and complete
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course));

            foreach ($course->lessons as $lesson) {
                $this->actingAs($learner)
                    ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                        'current_page' => 1,
                        'total_pages' => 1,
                    ]);
            }

            // 2. Rate the course
            $this->actingAs($learner)
                ->post(route('courses.ratings.store', $course), [
                    'rating' => 5,
                    'review' => 'Kursus yang sangat bagus dan bermanfaat!',
                ])
                ->assertRedirect();

            // 3. Verify rating exists
            $this->assertDatabaseHas('course_ratings', [
                'course_id' => $course->id,
                'user_id' => $learner->id,
                'rating' => 5,
            ]);

            // 4. Check learner dashboard shows completed course
            $this->actingAs($learner)
                ->get(route('learner.dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('myLearning', 1)
                    ->where('myLearning.0.course_id', $course->id)
                    ->where('myLearning.0.status', 'completed')
                );
        });

    });

    describe('Post-Completion Access', function () {

        it('completed learner can still view lessons after completion', function () {
            $course = createPublishedCourseWithContent(1, 2);
            $learner = User::factory()->create(['role' => 'learner']);

            // Enroll and complete
            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
                'progress_percentage' => 100,
            ]);

            // Should still be able to view lessons
            $lesson = $course->lessons->first();

            $this->actingAs($learner)
                ->get(route('courses.lessons.show', [$course, $lesson]))
                ->assertOk();
        });

        it('completed learner can view their assessment attempts', function () {
            $contentManager = User::factory()->create(['role' => 'content_manager']);
            $course = Course::factory()->published()->create([
                'user_id' => $contentManager->id,
            ]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $contentManager->id,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);

            Enrollment::factory()->completed()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            $attempt = AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
            ]);

            // Should be able to view attempt result
            $this->actingAs($learner)
                ->get(route('assessments.attempt', [$course, $assessment, $attempt]))
                ->assertOk();
        });

    });

    describe('Edge Cases', function () {

        it('handles course with no lessons gracefully', function () {
            $course = Course::factory()->published()->create(['visibility' => 'public']);
            $learner = User::factory()->create(['role' => 'learner']);

            // Can enroll
            $this->actingAs($learner)
                ->post(route('courses.enroll', $course))
                ->assertRedirect();

            $enrollment = Enrollment::forUser($learner)->first();

            // Progress should be 0 for empty course
            expect($enrollment->progress_percentage)->toBe(0);
            expect($enrollment->is_active)->toBeTrue();
        });

        it('multiple learners have independent progress', function () {
            $course = createPublishedCourseWithContent(1, 3);

            $learner1 = User::factory()->create(['role' => 'learner']);
            $learner2 = User::factory()->create(['role' => 'learner']);

            // Both enroll
            $this->actingAs($learner1)->post(route('courses.enroll', $course));
            $this->actingAs($learner2)->post(route('courses.enroll', $course));

            $lessons = $course->lessons;

            // Learner 1 completes all lessons
            foreach ($lessons as $lesson) {
                $this->actingAs($learner1)
                    ->patch(route('courses.lessons.progress.update', [$course, $lesson]), [
                        'current_page' => 1,
                        'total_pages' => 1,
                    ]);
            }

            // Learner 2 completes only first lesson
            $this->actingAs($learner2)
                ->patch(route('courses.lessons.progress.update', [$course, $lessons->first()]), [
                    'current_page' => 1,
                    'total_pages' => 1,
                ]);

            $enrollment1 = Enrollment::where('user_id', $learner1->id)->first();
            $enrollment2 = Enrollment::where('user_id', $learner2->id)->first();

            expect($enrollment1->is_completed)->toBeTrue();
            expect($enrollment1->progress_percentage)->toBe(100);

            expect($enrollment2->is_active)->toBeTrue();
            expect($enrollment2->progress_percentage)->toBe(33.3);
        });

        it('prevents double enrollment', function () {
            $course = createPublishedCourseWithContent();
            $learner = User::factory()->create(['role' => 'learner']);

            // First enrollment
            $this->actingAs($learner)->post(route('courses.enroll', $course));

            // Second enrollment attempt
            $this->actingAs($learner)->post(route('courses.enroll', $course));

            // Should still have only 1 enrollment
            $enrollmentCount = Enrollment::where('user_id', $learner->id)
                ->where('course_id', $course->id)
                ->count();

            expect($enrollmentCount)->toBe(1);
        });

    });

});
