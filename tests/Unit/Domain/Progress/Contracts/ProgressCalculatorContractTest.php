<?php

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Domain\Progress\Strategies\LessonBasedProgressCalculator;
use App\Domain\Progress\Strategies\WeightedProgressCalculator;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Contract test: All progress calculators must satisfy the same contract.
 *
 * This ensures that any new calculator implementation follows
 * the expected interface behavior and can be used interchangeably.
 */
describe('ProgressCalculatorContract', function () {

    $calculators = [
        'LessonBased' => fn () => new LessonBasedProgressCalculator,
        'Weighted' => fn () => new WeightedProgressCalculator,
        'AssessmentInclusive' => fn () => new AssessmentInclusiveProgressCalculator,
    ];

    foreach ($calculators as $name => $factory) {

        describe("{$name}Calculator contract compliance", function () use ($factory) {

            it('implements ProgressCalculatorContract', function () use ($factory) {
                $calculator = $factory();

                expect($calculator)->toBeInstanceOf(ProgressCalculatorContract::class);
            });

            it('calculate() returns float', function () use ($factory) {
                $calculator = $factory();
                $enrollment = Enrollment::factory()->create();

                $progress = $calculator->calculate($enrollment);

                expect($progress)->toBeFloat();
            });

            it('calculate() returns non-negative value', function () use ($factory) {
                $calculator = $factory();
                $enrollment = Enrollment::factory()->create();

                $progress = $calculator->calculate($enrollment);

                expect($progress)->toBeGreaterThanOrEqual(0);
            });

            it('calculate() returns value not exceeding 100', function () use ($factory) {
                $calculator = $factory();

                $course = Course::factory()->create();
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                $lessons = Lesson::factory()->count(3)->create([
                    'course_section_id' => $section->id,
                    'estimated_duration_minutes' => 30,
                ]);

                $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

                // Complete all lessons
                foreach ($lessons as $lesson) {
                    LessonProgress::factory()->completed()->create([
                        'enrollment_id' => $enrollment->id,
                        'lesson_id' => $lesson->id,
                    ]);
                }

                $progress = $calculator->calculate($enrollment);

                expect($progress)->toBeLessThanOrEqual(100);
            });

            it('calculate() handles empty course gracefully', function () use ($factory) {
                $calculator = $factory();
                $enrollment = Enrollment::factory()->create();

                // Should not throw exception
                $progress = $calculator->calculate($enrollment);

                expect($progress)->toBeFloat();
            });

            it('isComplete() returns boolean', function () use ($factory) {
                $calculator = $factory();
                $enrollment = Enrollment::factory()->create();

                $isComplete = $calculator->isComplete($enrollment);

                expect($isComplete)->toBeBool();
            });

            it('isComplete() returns false for course with uncompleted lessons', function () use ($factory) {
                $calculator = $factory();

                $course = Course::factory()->create();
                $section = CourseSection::factory()->create(['course_id' => $course->id]);
                Lesson::factory()->count(5)->create([
                    'course_section_id' => $section->id,
                    'estimated_duration_minutes' => 30,
                ]);

                $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

                // No progress made

                $isComplete = $calculator->isComplete($enrollment);

                expect($isComplete)->toBeFalse();
            });

            it('getName() returns non-empty string', function () use ($factory) {
                $calculator = $factory();

                $name = $calculator->getName();

                expect($name)->toBeString();
                expect($name)->not->toBeEmpty();
            });

            it('getName() returns valid type identifier', function () use ($factory) {
                $calculator = $factory();

                $name = $calculator->getName();

                // Should be snake_case identifier
                expect($name)->toMatch('/^[a-z][a-z0-9_]*$/');
            });
        });
    }

    describe('cross-calculator consistency', function () {

        it('lesson-only calculators agree on 0% for no progress', function () {
            // Only LessonBased and Weighted calculators return 0% for no lesson progress
            // AssessmentInclusive returns 30% because no assessments = 100% assessment progress
            $calculators = [
                'LessonBased' => fn () => new LessonBasedProgressCalculator,
                'Weighted' => fn () => new WeightedProgressCalculator,
            ];

            $course = Course::factory()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            Lesson::factory()->count(3)->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 30,
            ]);

            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            foreach ($calculators as $name => $factory) {
                $calculator = $factory();
                $progress = $calculator->calculate($enrollment);

                expect($progress)->toBe(0.0, "{$name} calculator should return 0 for no progress");
            }
        });

        it('assessment-inclusive calculator factors in assessment weight', function () {
            $calculator = new AssessmentInclusiveProgressCalculator;

            $course = Course::factory()->create();
            $section = CourseSection::factory()->create(['course_id' => $course->id]);
            Lesson::factory()->count(3)->create([
                'course_section_id' => $section->id,
                'estimated_duration_minutes' => 30,
            ]);

            $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

            // No lessons completed, no assessments = (0 * 0.7) + (100 * 0.3) = 30%
            $progress = $calculator->calculate($enrollment);

            expect($progress)->toBe(30.0);
        });

        it('all calculators return unique names', function () {
            $calculators = [
                'LessonBased' => fn () => new LessonBasedProgressCalculator,
                'Weighted' => fn () => new WeightedProgressCalculator,
                'AssessmentInclusive' => fn () => new AssessmentInclusiveProgressCalculator,
            ];

            $names = [];

            foreach ($calculators as $factory) {
                $calculator = $factory();
                $names[] = $calculator->getName();
            }

            $uniqueNames = array_unique($names);

            expect(count($uniqueNames))->toBe(count($names), 'All calculator names should be unique');
        });
    });
});
