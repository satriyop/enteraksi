<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Percentage implements JsonSerializable, Stringable
{
    public function __construct(
        public float $value
    ) {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException(
                "Percentage must be between 0 and 100, got: {$value}"
            );
        }
    }

    public static function fromFraction(float $numerator, float $denominator): self
    {
        if ($denominator === 0.0) {
            return new self(0);
        }

        return new self(round(($numerator / $denominator) * 100, 2));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function full(): self
    {
        return new self(100);
    }

    public function isComplete(): bool
    {
        return $this->value >= 100;
    }

    public function toFraction(): float
    {
        return $this->value / 100;
    }

    public function format(int $decimals = 1): string
    {
        return number_format($this->value, $decimals).'%';
    }

    public function jsonSerialize(): float
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
