<?php

use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AssessmentInclusiveProgressCalculator', function () {
    beforeEach(function () {
        $this->calculator = new AssessmentInclusiveProgressCalculator;
    });

    it('returns 100 for course with no lessons and no assessments', function () {
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        $progress = $this->calculator->calculate($enrollment);

        // No lessons = 100% lesson progress, no assessments = 100% assessment progress
        // (100 * 0.7) + (100 * 0.3) = 100
        expect($progress)->toBe(100.0);
    });

    it('calculates progress with only lessons (no assessments)', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(4)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Complete 2 of 4 lessons (50% lesson progress)
        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons[0]->id,
        ]);
        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons[1]->id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        // (50 * 0.7) + (100 * 0.3) = 35 + 30 = 65
        expect($progress)->toBe(65.0);
    });

    it('calculates progress with only assessments (no lessons)', function () {
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Create 2 published assessments
        $assessment1 = Assessment::factory()->published()->create(['course_id' => $course->id]);
        $assessment2 = Assessment::factory()->published()->create(['course_id' => $course->id]);

        // Pass 1 of 2 assessments (50% assessment progress)
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $assessment1->id,
            'user_id' => $enrollment->user_id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        // No lessons = 100% lesson progress
        // (100 * 0.7) + (50 * 0.3) = 70 + 15 = 85
        expect($progress)->toBe(85.0);
    });

    it('calculates combined progress with lessons and assessments', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Create 2 published assessments
        $assessment1 = Assessment::factory()->published()->create(['course_id' => $course->id]);
        $assessment2 = Assessment::factory()->published()->create(['course_id' => $course->id]);

        // Complete all lessons (100% lesson progress)
        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        // Pass 1 of 2 assessments (50% assessment progress)
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $assessment1->id,
            'user_id' => $enrollment->user_id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        // (100 * 0.7) + (50 * 0.3) = 70 + 15 = 85
        expect($progress)->toBe(85.0);
    });

    it('reaches 100 percent when all lessons and assessments completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Create 2 published assessments
        $assessment1 = Assessment::factory()->published()->create(['course_id' => $course->id]);
        $assessment2 = Assessment::factory()->published()->create(['course_id' => $course->id]);

        // Complete all lessons
        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        // Pass all assessments
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $assessment1->id,
            'user_id' => $enrollment->user_id,
        ]);
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $assessment2->id,
            'user_id' => $enrollment->user_id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(100.0);
    });

    it('ignores draft assessments in progress calculation', function () {
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Create draft assessment (should be ignored)
        Assessment::factory()->draft()->create(['course_id' => $course->id]);

        $progress = $this->calculator->calculate($enrollment);

        // No lessons = 100%, draft assessment ignored = 100%
        expect($progress)->toBe(100.0);
    });

    it('isComplete returns true when all lessons completed and no assessments', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        $isComplete = $this->calculator->isComplete($enrollment);

        expect($isComplete)->toBeTrue();
    });

    it('isComplete returns false when lessons incomplete', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        $isComplete = $this->calculator->isComplete($enrollment);

        expect($isComplete)->toBeFalse();
    });

    it('isComplete returns true when lessons completed and no required assessments exist', function () {
        // Optional assessments (is_required = false) don't block completion.
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Complete lessons
        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        // Create optional assessment (is_required = false)
        $assessment = Assessment::factory()->published()->optional()->create(['course_id' => $course->id]);

        // Create failed attempt - since assessment is optional, it doesn't block completion
        AssessmentAttempt::factory()->failed()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $enrollment->user_id,
        ]);

        $isComplete = $this->calculator->isComplete($enrollment);

        // Course is complete because all lessons are done and assessment is optional
        expect($isComplete)->toBeTrue();
    });

    it('isComplete returns true when all lessons and assessments completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(2)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Create published assessment
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id]);

        // Complete lessons
        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        // Pass assessment
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $enrollment->user_id,
        ]);

        $isComplete = $this->calculator->isComplete($enrollment);

        expect($isComplete)->toBeTrue();
    });

    it('returns correct name', function () {
        expect($this->calculator->getName())->toBe('assessment_inclusive');
    });

    describe('edge cases', function () {
        it('returns 0% assessment progress when no attempts exist', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            // Create required assessment but no attempts
            Assessment::factory()->published()->required()->create(['course_id' => $course->id]);

            $progress = $this->calculator->calculate($enrollment);

            // No lessons = 100%, no passed assessments = 0%
            // (100 * 0.7) + (0 * 0.3) = 70 + 0 = 70
            expect($progress)->toBe(70.0);
        });

        it('returns 0% assessment progress when all attempts failed', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            $assessment = Assessment::factory()->published()->required()->create(['course_id' => $course->id]);

            // Create failed attempts
            AssessmentAttempt::factory()->failed()->count(3)->create([
                'assessment_id' => $assessment->id,
                'user_id' => $enrollment->user_id,
            ]);

            $progress = $this->calculator->calculate($enrollment);

            // (100 * 0.7) + (0 * 0.3) = 70
            expect($progress)->toBe(70.0);
        });

        it('counts assessment as passed with multiple attempts where at least one passed', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            $assessment = Assessment::factory()->published()->required()->create(['course_id' => $course->id]);

            // Create failed attempts followed by a passed one (retake scenario)
            AssessmentAttempt::factory()->failed()->count(2)->create([
                'assessment_id' => $assessment->id,
                'user_id' => $enrollment->user_id,
            ]);
            AssessmentAttempt::factory()->passed()->create([
                'assessment_id' => $assessment->id,
                'user_id' => $enrollment->user_id,
            ]);

            $progress = $this->calculator->calculate($enrollment);

            // (100 * 0.7) + (100 * 0.3) = 100
            expect($progress)->toBe(100.0);
        });

        it('ignores optional assessments in progress calculation', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            // Create 1 required and 1 optional assessment
            $required = Assessment::factory()->published()->required()->create(['course_id' => $course->id]);
            Assessment::factory()->published()->optional()->create(['course_id' => $course->id]);

            // Pass only the required assessment
            AssessmentAttempt::factory()->passed()->create([
                'assessment_id' => $required->id,
                'user_id' => $enrollment->user_id,
            ]);

            $progress = $this->calculator->calculate($enrollment);

            // Only required assessments count: 1/1 passed = 100%
            // (100 * 0.7) + (100 * 0.3) = 100
            expect($progress)->toBe(100.0);
        });

        it('isComplete is false when required assessment has only failed attempts', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            $assessment = Assessment::factory()->published()->required()->create(['course_id' => $course->id]);

            // Create failed attempts
            AssessmentAttempt::factory()->failed()->count(2)->create([
                'assessment_id' => $assessment->id,
                'user_id' => $enrollment->user_id,
            ]);

            $isComplete = $this->calculator->isComplete($enrollment);

            expect($isComplete)->toBeFalse();
        });
    });

    describe('getAssessmentStats', function () {
        it('returns zero stats for course with no assessments', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            $stats = $this->calculator->getAssessmentStats($enrollment);

            expect($stats)->toBe([
                'total' => 0,
                'passed' => 0,
                'pending' => 0,
                'required_total' => 0,
                'required_passed' => 0,
            ]);
        });

        it('returns correct stats with mixed required and optional assessments', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            // 2 required, 1 optional
            $required1 = Assessment::factory()->published()->required()->create(['course_id' => $course->id]);
            $required2 = Assessment::factory()->published()->required()->create(['course_id' => $course->id]);
            $optional = Assessment::factory()->published()->optional()->create(['course_id' => $course->id]);

            // Pass 1 required and the optional
            AssessmentAttempt::factory()->passed()->create([
                'assessment_id' => $required1->id,
                'user_id' => $enrollment->user_id,
            ]);
            AssessmentAttempt::factory()->passed()->create([
                'assessment_id' => $optional->id,
                'user_id' => $enrollment->user_id,
            ]);

            $stats = $this->calculator->getAssessmentStats($enrollment);

            expect($stats)->toBe([
                'total' => 3,
                'passed' => 2,
                'pending' => 1,
                'required_total' => 2,
                'required_passed' => 1,
            ]);
        });

        it('excludes draft assessments from stats', function () {
            $course = Course::factory()->create();
            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            Assessment::factory()->published()->required()->create(['course_id' => $course->id]);
            Assessment::factory()->draft()->required()->create(['course_id' => $course->id]);

            $stats = $this->calculator->getAssessmentStats($enrollment);

            expect($stats['total'])->toBe(1);
            expect($stats['required_total'])->toBe(1);
        });
    });
});
