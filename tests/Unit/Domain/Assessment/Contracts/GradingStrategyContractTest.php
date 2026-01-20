<?php

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\DTOs\GradingResult;
use App\Domain\Assessment\Strategies\ManualGradingStrategy;
use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Domain\Assessment\Strategies\ShortAnswerGradingStrategy;
use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Contract test: All grading strategies must satisfy the same contract.
 *
 * This ensures that any new grading strategy implementation follows
 * the expected interface behavior and can be used interchangeably.
 */
describe('GradingStrategyContract', function () {

    $strategies = [
        'MultipleChoice' => fn () => new MultipleChoiceGradingStrategy,
        'TrueFalse' => fn () => new TrueFalseGradingStrategy,
        'ShortAnswer' => fn () => new ShortAnswerGradingStrategy,
        'ManualGrading' => fn () => new ManualGradingStrategy,
    ];

    foreach ($strategies as $name => $factory) {

        describe("{$name}Strategy contract compliance", function () use ($factory) {

            it('implements GradingStrategyContract', function () use ($factory) {
                $strategy = $factory();

                expect($strategy)->toBeInstanceOf(GradingStrategyContract::class);
            });

            it('returns non-empty array of handled types', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                expect($types)->toBeArray();
                expect($types)->not->toBeEmpty();

                foreach ($types as $type) {
                    expect($type)->toBeString();
                    expect($type)->not->toBeEmpty();
                }
            });

            it('supports() returns boolean', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                // Test with a supported question type
                $question = Mockery::mock(Question::class)->makePartial();
                $question->question_type = $types[0];

                $supports = $strategy->supports($question);

                expect($supports)->toBeBool();
            });

            it('supports() returns true for handled types', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                foreach ($types as $type) {
                    $question = Mockery::mock(Question::class)->makePartial();
                    $question->question_type = $type;

                    expect($strategy->supports($question))->toBeTrue(
                        "Strategy should support question type: {$type}"
                    );
                }
            });

            it('supports() returns false for unhandled types', function () use ($factory) {
                $strategy = $factory();
                $handledTypes = $strategy->getHandledTypes();

                // Use a type that no strategy handles
                $question = Mockery::mock(Question::class)->makePartial();
                $question->question_type = 'definitely_not_a_real_question_type_xyz';

                expect($strategy->supports($question))->toBeFalse();
            });

            it('grade() returns GradingResult with required properties', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                $question = Question::factory()->create([
                    'question_type' => $types[0],
                    'points' => 10,
                ]);

                // Add options for types that need them
                if (in_array($types[0], ['single_choice', 'multiple_choice', 'true_false'])) {
                    QuestionOption::factory()->create([
                        'question_id' => $question->id,
                        'is_correct' => true,
                    ]);
                }

                $result = $strategy->grade($question, 'test answer');

                expect($result)->toBeInstanceOf(GradingResult::class);
                expect($result)->toHaveProperty('isCorrect');
                expect($result)->toHaveProperty('score');
                expect($result)->toHaveProperty('maxScore');
                expect($result)->toHaveProperty('metadata');
            });

            it('grade() returns correct maxScore from question points', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();
                $expectedPoints = 15;

                $question = Question::factory()->create([
                    'question_type' => $types[0],
                    'points' => $expectedPoints,
                ]);

                // Add options for types that need them
                if (in_array($types[0], ['single_choice', 'multiple_choice', 'true_false'])) {
                    QuestionOption::factory()->create([
                        'question_id' => $question->id,
                        'is_correct' => true,
                    ]);
                }

                $result = $strategy->grade($question, 'any answer');

                expect($result->maxScore)->toBe((float) $expectedPoints);
            });

            it('grade() isCorrect is boolean', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                $question = Question::factory()->create([
                    'question_type' => $types[0],
                    'points' => 10,
                ]);

                // Add options for types that need them
                if (in_array($types[0], ['single_choice', 'multiple_choice', 'true_false'])) {
                    QuestionOption::factory()->create([
                        'question_id' => $question->id,
                        'is_correct' => true,
                    ]);
                }

                $result = $strategy->grade($question, 'any answer');

                expect($result->isCorrect)->toBeBool();
            });

            it('grade() score is non-negative float', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                $question = Question::factory()->create([
                    'question_type' => $types[0],
                    'points' => 10,
                ]);

                // Add options for types that need them
                if (in_array($types[0], ['single_choice', 'multiple_choice', 'true_false'])) {
                    QuestionOption::factory()->create([
                        'question_id' => $question->id,
                        'is_correct' => true,
                    ]);
                }

                $result = $strategy->grade($question, 'any answer');

                expect($result->score)->toBeFloat();
                expect($result->score)->toBeGreaterThanOrEqual(0);
            });

            it('grade() score does not exceed maxScore', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                $question = Question::factory()->create([
                    'question_type' => $types[0],
                    'points' => 10,
                ]);

                // Add options for types that need them
                if (in_array($types[0], ['single_choice', 'multiple_choice', 'true_false'])) {
                    QuestionOption::factory()->create([
                        'question_id' => $question->id,
                        'is_correct' => true,
                    ]);
                }

                $result = $strategy->grade($question, 'any answer');

                expect($result->score)->toBeLessThanOrEqual($result->maxScore);
            });

            it('grade() metadata is array', function () use ($factory) {
                $strategy = $factory();
                $types = $strategy->getHandledTypes();

                $question = Question::factory()->create([
                    'question_type' => $types[0],
                    'points' => 10,
                ]);

                // Add options for types that need them
                if (in_array($types[0], ['single_choice', 'multiple_choice', 'true_false'])) {
                    QuestionOption::factory()->create([
                        'question_id' => $question->id,
                        'is_correct' => true,
                    ]);
                }

                $result = $strategy->grade($question, 'any answer');

                expect($result->metadata)->toBeArray();
            });
        });
    }
});
