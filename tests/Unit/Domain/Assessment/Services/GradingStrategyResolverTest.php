<?php

use App\Domain\Assessment\Services\GradingStrategyResolver;
use App\Domain\Assessment\Strategies\ManualGradingStrategy;
use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Domain\Assessment\Strategies\ShortAnswerGradingStrategy;
use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GradingStrategyResolver', function () {
    beforeEach(function () {
        $this->resolver = new GradingStrategyResolver([
            new MultipleChoiceGradingStrategy,
            new TrueFalseGradingStrategy,
            new ShortAnswerGradingStrategy,
            new ManualGradingStrategy,
        ]);
    });

    it('resolves multiple choice strategy', function () {
        $question = Question::factory()->multipleChoice()->create();

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(MultipleChoiceGradingStrategy::class);
    });

    it('resolves true/false strategy', function () {
        $question = Question::factory()->trueFalse()->create();

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(TrueFalseGradingStrategy::class);
    });

    it('resolves short answer strategy', function () {
        $question = Question::factory()->shortAnswer()->create();

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(ShortAnswerGradingStrategy::class);
    });

    it('resolves manual grading for essays', function () {
        $question = Question::factory()->essay()->create();

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(ManualGradingStrategy::class);
    });

    it('resolves manual grading for file uploads', function () {
        $question = Question::factory()->fileUpload()->create();

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeInstanceOf(ManualGradingStrategy::class);
    });

    it('returns null for unsupported types', function () {
        // Create a partial mock to test unsupported types
        // The database has CHECK constraints, so we use makePartial
        $question = Mockery::mock(Question::class)->makePartial();
        $question->question_type = 'unknown_unsupported_type';

        $strategy = $this->resolver->resolve($question);

        expect($strategy)->toBeNull();
    });

    it('lists all supported types', function () {
        $types = $this->resolver->getSupportedTypes();

        expect($types)->toContain('multiple_choice');
        expect($types)->toContain('true_false');
        expect($types)->toContain('short_answer');
        expect($types)->toContain('essay');
    });

    it('returns all strategies', function () {
        $strategies = $this->resolver->getAllStrategies();

        expect($strategies)->toHaveCount(4);
    });
});
