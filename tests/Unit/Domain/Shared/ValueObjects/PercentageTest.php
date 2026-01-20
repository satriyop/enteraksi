<?php

use App\Domain\Shared\ValueObjects\Percentage;

describe('Percentage', function () {
    it('creates valid percentage', function () {
        $percentage = new Percentage(75.5);
        expect($percentage->value)->toBe(75.5);
    });

    it('rejects negative values', function () {
        new Percentage(-1);
    })->throws(InvalidArgumentException::class);

    it('rejects values over 100', function () {
        new Percentage(101);
    })->throws(InvalidArgumentException::class);

    it('accepts boundary values', function () {
        $zero = new Percentage(0);
        $hundred = new Percentage(100);

        expect($zero->value)->toBe(0.0);
        expect($hundred->value)->toBe(100.0);
    });

    it('calculates from fraction', function () {
        $percentage = Percentage::fromFraction(3, 4);
        expect($percentage->value)->toBe(75.0);
    });

    it('handles zero denominator', function () {
        $percentage = Percentage::fromFraction(5, 0);
        expect($percentage->value)->toBe(0.0);
    });

    it('formats correctly', function () {
        $percentage = new Percentage(75.55);
        expect($percentage->format())->toBe('75.6%');
        expect($percentage->format(2))->toBe('75.55%');
    });

    it('detects completion', function () {
        expect((new Percentage(100))->isComplete())->toBeTrue();
        expect((new Percentage(99.9))->isComplete())->toBeFalse();
    });

    it('converts to fraction', function () {
        $percentage = new Percentage(50);
        expect($percentage->toFraction())->toBe(0.5);
    });

    it('provides static factory methods', function () {
        expect(Percentage::zero()->value)->toBe(0.0);
        expect(Percentage::full()->value)->toBe(100.0);
    });

    it('serializes to JSON', function () {
        $percentage = new Percentage(75.5);
        expect($percentage->jsonSerialize())->toBe(75.5);
    });

    it('converts to string', function () {
        $percentage = new Percentage(75.5);
        expect((string) $percentage)->toBe('75.5%');
    });
});
