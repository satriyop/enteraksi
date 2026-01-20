<?php

use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Progress\Services\ProgressCalculatorFactory;
use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Domain\Progress\Strategies\LessonBasedProgressCalculator;
use App\Domain\Progress\Strategies\WeightedProgressCalculator;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ProgressCalculatorFactory', function () {
    beforeEach(function () {
        $this->factory = app(ProgressCalculatorFactory::class);
    });

    describe('resolve', function () {
        it('resolves lesson_based calculator', function () {
            $calculator = $this->factory->resolve('lesson_based');

            expect($calculator)->toBeInstanceOf(LessonBasedProgressCalculator::class);
            expect($calculator)->toBeInstanceOf(ProgressCalculatorContract::class);
        });

        it('resolves weighted calculator', function () {
            $calculator = $this->factory->resolve('weighted');

            expect($calculator)->toBeInstanceOf(WeightedProgressCalculator::class);
            expect($calculator)->toBeInstanceOf(ProgressCalculatorContract::class);
        });

        it('resolves assessment_inclusive calculator', function () {
            $calculator = $this->factory->resolve('assessment_inclusive');

            expect($calculator)->toBeInstanceOf(AssessmentInclusiveProgressCalculator::class);
            expect($calculator)->toBeInstanceOf(ProgressCalculatorContract::class);
        });

        it('defaults to lesson_based for unknown type', function () {
            $calculator = $this->factory->resolve('unknown_type');

            expect($calculator)->toBeInstanceOf(LessonBasedProgressCalculator::class);
        });
    });

    describe('forCourse', function () {
        it('uses course-specific calculator type when set', function () {
            $course = Course::factory()->create();
            // Simulate the attribute existing on the model
            $course->progress_calculator_type = 'weighted';

            $calculator = $this->factory->forCourse($course);

            expect($calculator)->toBeInstanceOf(WeightedProgressCalculator::class);
        });

        it('uses default config when course has no calculator type', function () {
            config(['lms.progress_calculator' => 'lesson_based']);

            $course = Course::factory()->create();
            // Simulate the attribute being null
            $course->progress_calculator_type = null;

            $calculator = $this->factory->forCourse($course);

            expect($calculator)->toBeInstanceOf(LessonBasedProgressCalculator::class);
        });

        it('respects assessment_inclusive course setting', function () {
            $course = Course::factory()->create();
            // Simulate the attribute existing on the model
            $course->progress_calculator_type = 'assessment_inclusive';

            $calculator = $this->factory->forCourse($course);

            expect($calculator)->toBeInstanceOf(AssessmentInclusiveProgressCalculator::class);
        });
    });

    describe('getDefault', function () {
        it('returns calculator based on configuration', function () {
            config(['lms.progress_calculator' => 'weighted']);

            $calculator = $this->factory->getDefault();

            expect($calculator)->toBeInstanceOf(WeightedProgressCalculator::class);
        });

        it('defaults to lesson_based when config set to lesson_based', function () {
            config(['lms.progress_calculator' => 'lesson_based']);

            $calculator = $this->factory->getDefault();

            expect($calculator)->toBeInstanceOf(LessonBasedProgressCalculator::class);
        });
    });

    describe('getAvailableTypes', function () {
        it('returns all available calculator types', function () {
            $types = $this->factory->getAvailableTypes();

            expect($types)->toBeArray();
            expect($types)->toHaveKeys(['lesson_based', 'weighted', 'assessment_inclusive']);
        });

        it('returns Indonesian labels', function () {
            $types = $this->factory->getAvailableTypes();

            expect($types['lesson_based'])->toBe('Berbasis Pelajaran');
            expect($types['weighted'])->toBe('Berbasis Durasi (Tertimbang)');
            expect($types['assessment_inclusive'])->toBe('Termasuk Penilaian');
        });
    });
});
