<?php

use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AssessmentInclusiveProgressCalculator with is_required column', function () {

    it('completes when all required assessments passed', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        // Create required and optional assessments
        $requiredAssessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);
        Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => false,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete lesson
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'current_page' => 1,
        ]);

        // Pass required assessment only
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $requiredAssessment->id,
            'user_id' => $user->id,
        ]);

        $calculator = new AssessmentInclusiveProgressCalculator;

        // Should be complete - optional assessment not required
        expect($calculator->isComplete($enrollment))->toBeTrue();
    });

    it('does not complete when required assessment not passed', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete lesson
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'current_page' => 1,
        ]);

        // No assessment attempt

        $calculator = new AssessmentInclusiveProgressCalculator;

        expect($calculator->isComplete($enrollment))->toBeFalse();
    });

    it('completes when no required assessments exist', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        // Only optional assessments
        Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => false,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete lesson
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'current_page' => 1,
        ]);

        $calculator = new AssessmentInclusiveProgressCalculator;

        // No required assessments = complete if lessons done
        expect($calculator->isComplete($enrollment))->toBeTrue();
    });

    it('completes when no assessments at all and lessons done', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        // No assessments

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Complete lesson
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'current_page' => 1,
        ]);

        $calculator = new AssessmentInclusiveProgressCalculator;

        // No assessments = complete if lessons done
        expect($calculator->isComplete($enrollment))->toBeTrue();
    });

    it('does not complete when lessons not done even with assessments passed', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['course_section_id' => $section->id]);

        $requiredAssessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'is_required' => true,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // No lesson progress

        // Pass assessment
        AssessmentAttempt::factory()->passed()->create([
            'assessment_id' => $requiredAssessment->id,
            'user_id' => $user->id,
        ]);

        $calculator = new AssessmentInclusiveProgressCalculator;

        // Lessons not done = not complete
        expect($calculator->isComplete($enrollment))->toBeFalse();
    });
});
