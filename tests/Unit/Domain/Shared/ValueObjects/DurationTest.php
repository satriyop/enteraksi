<?php

use App\Domain\Shared\ValueObjects\Duration;

describe('Duration', function () {
    it('creates valid duration from seconds', function () {
        $duration = new Duration(3600);
        expect($duration->seconds)->toBe(3600);
    });

    it('rejects negative duration', function () {
        new Duration(-1);
    })->throws(InvalidArgumentException::class);

    it('creates from minutes', function () {
        $duration = Duration::fromMinutes(5);
        expect($duration->seconds)->toBe(300);
    });

    it('creates from hours', function () {
        $duration = Duration::fromHours(2);
        expect($duration->seconds)->toBe(7200);
    });

    it('converts to minutes', function () {
        $duration = new Duration(150); // 2.5 minutes
        expect($duration->toMinutes())->toBe(2);
    });

    it('converts to hours', function () {
        $duration = new Duration(5400); // 1.5 hours
        expect($duration->toHours())->toBe(1.5);
    });

    it('adds durations together', function () {
        $duration1 = new Duration(60);
        $duration2 = new Duration(120);
        $total = $duration1->add($duration2);

        expect($total->seconds)->toBe(180);
    });

    it('formats seconds', function () {
        $duration = new Duration(45);
        expect($duration->format())->toBe('45 detik');
    });

    it('formats minutes', function () {
        $duration = new Duration(300); // 5 minutes
        expect($duration->format())->toBe('5 menit');
    });

    it('formats hours and minutes', function () {
        $duration = new Duration(5400); // 1 hour 30 minutes
        expect($duration->format())->toBe('1 jam 30 menit');
    });

    it('formats hours only when no remaining minutes', function () {
        $duration = new Duration(7200); // 2 hours
        expect($duration->format())->toBe('2 jam');
    });

    it('formats short', function () {
        $duration = new Duration(3723); // 1:02:03
        expect($duration->formatShort())->toBe('01:02:03');
    });

    it('provides zero factory method', function () {
        expect(Duration::zero()->seconds)->toBe(0);
    });

    it('serializes to JSON', function () {
        $duration = new Duration(3600);
        expect($duration->jsonSerialize())->toBe(3600);
    });

    it('converts to string', function () {
        $duration = new Duration(3600);
        expect((string) $duration)->toBe('1 jam');
    });
});
