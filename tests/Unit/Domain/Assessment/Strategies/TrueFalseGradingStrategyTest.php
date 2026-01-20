<?php

use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('TrueFalseGradingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new TrueFalseGradingStrategy;
    });

    it('supports true/false questions', function () {
        $question = Question::factory()->trueFalse()->create();

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('does not support multiple choice questions', function () {
        $question = Question::factory()->multipleChoice()->create();

        expect($this->strategy->supports($question))->toBeFalse();
    });

    it('grades "true" as correct when answer is true', function () {
        $question = Question::factory()->trueFalse()->create(['points' => 5]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'true',
        ]);

        $result = $this->strategy->grade($question, 'true');

        expect($result->isCorrect)->toBeTrue();
        expect($result->score)->toBe(5.0);
    });

    it('grades "benar" as correct (Indonesian)', function () {
        $question = Question::factory()->trueFalse()->create(['points' => 5]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'true',
        ]);

        $result = $this->strategy->grade($question, 'benar');

        expect($result->isCorrect)->toBeTrue();
    });

    it('grades "false" as incorrect when answer is true', function () {
        $question = Question::factory()->trueFalse()->create(['points' => 5]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'true',
        ]);

        $result = $this->strategy->grade($question, 'false');

        expect($result->isCorrect)->toBeFalse();
    });

    it('handles boolean values', function () {
        $question = Question::factory()->trueFalse()->create(['points' => 5]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'true',
        ]);

        $result = $this->strategy->grade($question, true);

        expect($result->isCorrect)->toBeTrue();
    });

    it('returns invalid feedback for unknown answer', function () {
        $question = Question::factory()->trueFalse()->create(['points' => 5]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'true',
        ]);

        $result = $this->strategy->grade($question, 'maybe');

        expect($result->isCorrect)->toBeFalse();
        expect($result->feedback)->toBe('Jawaban tidak valid.');
    });
});
