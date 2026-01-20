<?php

use App\Domain\Progress\Strategies\WeightedProgressCalculator;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('WeightedProgressCalculator', function () {
    beforeEach(function () {
        $this->calculator = new WeightedProgressCalculator;
    });

    it('returns 0 for course with no lessons', function () {
        $enrollment = Enrollment::factory()->create();

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(0.0);
    });

    it('falls back to lesson count when no duration data', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        // Create lessons without duration
        Lesson::factory()->count(4)->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 0,
        ]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(0.0);
    });

    it('calculates progress weighted by duration', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        // Lesson 1: 10 minutes
        $lesson1 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 10,
        ]);

        // Lesson 2: 30 minutes (heavier weight)
        $lesson2 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 30,
        ]);

        // Total: 40 minutes

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Complete only the short lesson (10 min / 40 min = 25%)
        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson1->id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(25.0);
    });

    it('calculates 100 percent when all weighted lessons completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $lesson1 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 15,
        ]);

        $lesson2 = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 45,
        ]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Complete both lessons
        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson1->id,
        ]);

        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson2->id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(100.0);
    });

    it('gives higher weight to longer lessons', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        // Short lesson: 10 min
        $shortLesson = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 10,
        ]);

        // Long lesson: 90 min
        $longLesson = Lesson::factory()->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 90,
        ]);

        // Total: 100 minutes

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Complete only the long lesson (90 min / 100 min = 90%)
        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $longLesson->id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(90.0);
    });

    it('isComplete returns false when not all completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        Lesson::factory()->count(2)->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 30,
        ]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        $isComplete = $this->calculator->isComplete($enrollment);

        expect($isComplete)->toBeFalse();
    });

    it('isComplete returns true when all completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $lessons = Lesson::factory()->count(2)->create([
            'course_section_id' => $section->id,
            'estimated_duration_minutes' => 30,
        ]);

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

    it('returns correct name', function () {
        expect($this->calculator->getName())->toBe('weighted');
    });
});
