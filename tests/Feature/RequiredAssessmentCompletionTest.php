<?php

use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

/**
 * Required Assessment Blocking Completion Tests
 *
 * These tests verify that required assessments properly block course completion
 * until they are passed. This is critical business logic for compliance training
 * where assessments must be passed before certification.
 *
 * Test Strategy:
 * - We use the AssessmentInclusiveProgressCalculator directly
 * - This ensures tests are isolated from config changes
 */
describe('Required Assessment Blocking Completion', function () {

    beforeEach(function () {
        $this->calculator = new AssessmentInclusiveProgressCalculator;
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);
    });

    describe('Basic Required Assessment Blocking', function () {

        it('blocks completion when required assessment not attempted', function () {
            // Create course with lesson and required assessment
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'is_required' => true,
                'passing_score' => 70,
            ]);

            // Learner completes all lessons
            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Should NOT be complete because required assessment not passed
            expect($this->calculator->isComplete($enrollment))->toBeFalse();
        });

        it('blocks completion when required assessment attempted but failed', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'is_required' => true,
                'passing_score' => 70,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Complete lesson
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Create failed assessment attempt
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'score' => 50,
                'max_score' => 100,
                'percentage' => 50,
                'passed' => false,
            ]);

            // Should NOT be complete because required assessment failed
            expect($this->calculator->isComplete($enrollment))->toBeFalse();
        });

        it('allows completion when required assessment passed', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'is_required' => true,
                'passing_score' => 70,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Complete lesson
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Create passed assessment attempt
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'score' => 85,
                'max_score' => 100,
                'percentage' => 85,
                'passed' => true,
            ]);

            // Should be complete
            expect($this->calculator->isComplete($enrollment))->toBeTrue();
        });

    });

    describe('Optional Assessment Behavior', function () {

        it('does not block completion when optional assessment not attempted', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Create OPTIONAL assessment (is_required = false)
            Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'is_required' => false,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Complete lesson
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Should be complete - optional assessment doesn't block
            expect($this->calculator->isComplete($enrollment))->toBeTrue();
        });

        it('does not block completion when optional assessment failed', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'is_required' => false, // Optional
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Create failed attempt on optional assessment
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'passed' => false,
            ]);

            // Should still be complete - optional assessment failure doesn't block
            expect($this->calculator->isComplete($enrollment))->toBeTrue();
        });

    });

    describe('Multiple Assessments', function () {

        it('requires ALL required assessments to be passed', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Create two required assessments
            $assessment1 = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'title' => 'Assessment 1',
                'is_required' => true,
            ]);

            $assessment2 = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'title' => 'Assessment 2',
                'is_required' => true,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Complete lesson
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Pass only first assessment
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment1->id,
                'user_id' => $learner->id,
                'passed' => true,
            ]);

            // Not complete yet - second required assessment not passed
            expect($this->calculator->isComplete($enrollment))->toBeFalse();

            // Now pass second assessment
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment2->id,
                'user_id' => $learner->id,
                'passed' => true,
            ]);

            // Now complete
            expect($this->calculator->isComplete($enrollment))->toBeTrue();
        });

        it('mixed required and optional assessments only require required ones', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Required assessment
            $requiredAssessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'title' => 'Required Assessment',
                'is_required' => true,
            ]);

            // Optional assessment
            Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'title' => 'Optional Assessment',
                'is_required' => false,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Pass only required assessment (don't attempt optional)
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $requiredAssessment->id,
                'user_id' => $learner->id,
                'passed' => true,
            ]);

            // Should be complete - only required assessment needs to be passed
            expect($this->calculator->isComplete($enrollment))->toBeTrue();
        });

    });

    describe('Draft Assessment Handling', function () {

        it('ignores draft required assessments for completion check', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            // Create draft required assessment (status = draft)
            Assessment::factory()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'status' => 'draft',
                'is_required' => true,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Should be complete - draft assessments are ignored
            expect($this->calculator->isComplete($enrollment))->toBeTrue();
        });

    });

    describe('Progress Percentage Calculation', function () {

        it('includes assessment progress in percentage calculation', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Complete lesson (100% lesson progress)
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // No assessment passed yet (0% assessment progress)
            // Expected: 70% (lesson weight) + 0% (assessment weight) = 70%
            $progress = $this->calculator->calculate($enrollment);
            expect($progress)->toBe(70.0);

            // Now pass assessment
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'passed' => true,
            ]);

            // Expected: 70% (lesson) + 30% (assessment) = 100%
            $progress = $this->calculator->calculate($enrollment);
            expect($progress)->toBe(100.0);
        });

    });

    describe('Lesson Incomplete Blocks Regardless of Assessment', function () {

        it('incomplete lessons block completion even with passed assessment', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            Lesson::factory()->count(2)->create(['course_section_id' => $section->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'is_required' => true,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            // Complete only 1 of 2 lessons
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $course->lessons->first()->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // Pass the assessment
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'passed' => true,
            ]);

            // Should NOT be complete - lessons not all done
            expect($this->calculator->isComplete($enrollment))->toBeFalse();
        });

    });

    describe('Attempt History', function () {

        it('only needs one passing attempt among multiple attempts', function () {
            $course = Course::factory()->published()->create([
                'user_id' => $this->contentManager->id,
            ]);

            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

            $assessment = Assessment::factory()->published()->create([
                'course_id' => $course->id,
                'user_id' => $this->contentManager->id,
                'is_required' => true,
                'max_attempts' => 3,
            ]);

            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::factory()->active()->create([
                'user_id' => $learner->id,
                'course_id' => $course->id,
            ]);

            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'current_page' => 1,
            ]);

            // First attempt - failed
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'attempt_number' => 1,
                'passed' => false,
            ]);

            expect($this->calculator->isComplete($enrollment))->toBeFalse();

            // Second attempt - passed
            AssessmentAttempt::factory()->graded()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $learner->id,
                'attempt_number' => 2,
                'passed' => true,
            ]);

            // Should be complete now
            expect($this->calculator->isComplete($enrollment))->toBeTrue();
        });

    });

});
