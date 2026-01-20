<?php

use App\Domain\Assessment\Strategies\ManualGradingStrategy;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ManualGradingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new ManualGradingStrategy;
    });

    it('supports essay questions', function () {
        $question = Question::factory()->essay()->create();

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('supports file upload questions', function () {
        $question = Question::factory()->fileUpload()->create();

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('supports matching questions', function () {
        $question = Question::factory()->matching()->create();

        expect($this->strategy->supports($question))->toBeTrue();
    });

    it('does not support multiple choice questions', function () {
        $question = Question::factory()->multipleChoice()->create();

        expect($this->strategy->supports($question))->toBeFalse();
    });

    it('always returns pending result', function () {
        $question = Question::factory()->essay()->create(['points' => 20]);

        $result = $this->strategy->grade($question, 'This is my essay answer.');

        expect($result->isCorrect)->toBeFalse();
        expect($result->score)->toBe(0.0);
        expect($result->maxScore)->toBe(20.0);
        expect($result->feedback)->toBe('Menunggu penilaian instruktur.');
        expect($result->metadata['requires_manual_grading'])->toBeTrue();
    });

    it('returns all supported types', function () {
        $types = $this->strategy->getHandledTypes();

        expect($types)->toContain('essay');
        expect($types)->toContain('file_upload');
        expect($types)->toContain('matching');
    });
});
