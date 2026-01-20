<?php

use App\Domain\Assessment\ValueObjects\Score;

describe('Score', function () {
    it('creates valid score', function () {
        $score = new Score(80, 100);
        expect($score->earned)->toBe(80.0);
        expect($score->maximum)->toBe(100.0);
    });

    it('calculates percentage', function () {
        $score = new Score(75, 100);
        expect($score->getPercentage())->toBe(75.0);
    });

    it('determines passing status', function () {
        $score = new Score(70, 100);
        expect($score->isPassing(70))->toBeTrue();
        expect($score->isPassing(71))->toBeFalse();
    });

    it('detects perfect score', function () {
        $perfect = new Score(100, 100);
        $partial = new Score(90, 100);

        expect($perfect->isPerfect())->toBeTrue();
        expect($partial->isPerfect())->toBeFalse();
    });

    it('adds scores together', function () {
        $score1 = new Score(10, 20);
        $score2 = new Score(15, 30);
        $total = $score1->add($score2);

        expect($total->earned)->toBe(25.0);
        expect($total->maximum)->toBe(50.0);
    });

    it('rejects negative earned score', function () {
        new Score(-1, 100);
    })->throws(InvalidArgumentException::class);

    it('rejects zero maximum score', function () {
        new Score(0, 0);
    })->throws(InvalidArgumentException::class);

    it('rejects earned greater than maximum', function () {
        new Score(101, 100);
    })->throws(InvalidArgumentException::class);

    it('provides static factory methods', function () {
        $zero = Score::zero(100);
        $perfect = Score::perfect(100);

        expect($zero->earned)->toBe(0.0);
        expect($zero->maximum)->toBe(100.0);
        expect($perfect->earned)->toBe(100.0);
        expect($perfect->maximum)->toBe(100.0);
    });

    it('formats correctly', function () {
        $score = new Score(75, 100);
        expect($score->format())->toBe('75.0/100.0 (75.0%)');
    });

    it('serializes to JSON', function () {
        $score = new Score(75, 100);
        $json = $score->jsonSerialize();

        expect($json['earned'])->toBe(75.0);
        expect($json['maximum'])->toBe(100.0);
        expect($json['percentage'])->toBe(75.0);
    });

    it('converts to string', function () {
        $score = new Score(75, 100);
        expect((string) $score)->toBe('75.0/100.0 (75.0%)');
    });
});
