<?php

use App\Domain\Progress\Strategies\LessonBasedProgressCalculator;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('LessonBasedProgressCalculator', function () {
    beforeEach(function () {
        $this->calculator = new LessonBasedProgressCalculator;
    });

    it('returns 0 for course with no lessons', function () {
        $enrollment = Enrollment::factory()->create();

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(0.0);
    });

    it('returns 0 when no lessons completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->count(5)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(0.0);
    });

    it('calculates correct percentage when some lessons completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(4)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        // Complete 2 of 4 lessons
        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons[0]->id,
        ]);

        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons[1]->id,
        ]);

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(50.0);
    });

    it('returns 100 when all lessons completed', function () {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(3)->create(['course_section_id' => $section->id]);

        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        $progress = $this->calculator->calculate($enrollment);

        expect($progress)->toBe(100.0);
    });

    it('isComplete returns false for course with no lessons', function () {
        $enrollment = Enrollment::factory()->create();

        $isComplete = $this->calculator->isComplete($enrollment);

        expect($isComplete)->toBeFalse();
    });

    it('isComplete returns true when all lessons completed', function () {
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

    it('returns correct name', function () {
        expect($this->calculator->getName())->toBe('lesson_based');
    });
});
