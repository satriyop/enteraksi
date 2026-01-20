<?php

use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MultipleChoiceGradingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new MultipleChoiceGradingStrategy;
    });

    it('supports multiple choice questions', function () {
        $question = Question::factory()->multipleChoice()->create();

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('does not support essay questions', function () {
        $question = Question::factory()->essay()->create();

        expect($this->strategy->supports($question))->toBeFalse();
    });

    it('grades correct single choice answer', function () {
        $question = Question::factory()->multipleChoice()->create(['points' => 10]);

        $correctOption = QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'Correct Answer',
        ]);

        QuestionOption::factory()->incorrect()->create([
            'question_id' => $question->id,
            'option_text' => 'Wrong Answer',
        ]);

        $result = $this->strategy->grade($question, $correctOption->id);

        expect($result->isCorrect)->toBeTrue();
        expect($result->score)->toBe(10.0);
        expect($result->feedback)->toBe('Jawaban benar!');
    });

    it('grades incorrect single choice answer', function () {
        $question = Question::factory()->multipleChoice()->create(['points' => 10]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
        ]);

        $wrongOption = QuestionOption::factory()->incorrect()->create([
            'question_id' => $question->id,
        ]);

        $result = $this->strategy->grade($question, $wrongOption->id);

        expect($result->isCorrect)->toBeFalse();
        expect($result->score)->toBe(0.0);
    });

    it('returns supported types', function () {
        $types = $this->strategy->getHandledTypes();

        expect($types)->toContain('multiple_choice');
        expect($types)->toContain('single_choice');
    });
});
