<?php

use App\Domain\Assessment\Strategies\ShortAnswerGradingStrategy;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ShortAnswerGradingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new ShortAnswerGradingStrategy;
    });

    it('supports short answer questions', function () {
        $question = Question::factory()->shortAnswer()->create();

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('does not support essay questions', function () {
        $question = Question::factory()->essay()->create();

        expect($this->strategy->supports($question))->toBeFalse();
    });

    it('grades exact match as correct', function () {
        $question = Question::factory()->shortAnswer()->create(['points' => 10]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'Jakarta',
        ]);

        $result = $this->strategy->grade($question, 'Jakarta');

        expect($result->isCorrect)->toBeTrue();
        expect($result->score)->toBe(10.0);
    });

    it('grades case-insensitive match as correct by default', function () {
        $question = Question::factory()->shortAnswer()->create(['points' => 10]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'Jakarta',
        ]);

        $result = $this->strategy->grade($question, 'jakarta');

        expect($result->isCorrect)->toBeTrue();
    });

    it('grades incorrect answer', function () {
        $question = Question::factory()->shortAnswer()->create(['points' => 10]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'option_text' => 'Jakarta',
        ]);

        $result = $this->strategy->grade($question, 'Surabaya');

        expect($result->isCorrect)->toBeFalse();
    });

    it('requires manual grading when no acceptable answers defined', function () {
        $question = Question::factory()->shortAnswer()->create(['points' => 10]);

        $result = $this->strategy->grade($question, 'Some answer');

        expect($result->metadata)->toHaveKey('requires_manual_grading');
        expect($result->metadata['requires_manual_grading'])->toBeTrue();
    });

    it('returns incorrect for empty answer', function () {
        $question = Question::factory()->shortAnswer()->create(['points' => 10]);

        $result = $this->strategy->grade($question, '');

        expect($result->isCorrect)->toBeFalse();
        expect($result->feedback)->toBe('Tidak ada jawaban.');
    });
});
