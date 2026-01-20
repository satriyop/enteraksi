<?php

namespace App\Domain\Assessment\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Score implements JsonSerializable, Stringable
{
    public function __construct(
        public float $earned,
        public float $maximum
    ) {
        if ($earned < 0) {
            throw new InvalidArgumentException('Earned score cannot be negative');
        }
        if ($maximum <= 0) {
            throw new InvalidArgumentException('Maximum score must be positive');
        }
        if ($earned > $maximum) {
            throw new InvalidArgumentException('Earned score cannot exceed maximum');
        }
    }

    public static function zero(float $maximum): self
    {
        return new self(0, $maximum);
    }

    public static function perfect(float $maximum): self
    {
        return new self($maximum, $maximum);
    }

    public function getPercentage(): float
    {
        return round(($this->earned / $this->maximum) * 100, 2);
    }

    public function isPassing(float $passingPercentage): bool
    {
        return $this->getPercentage() >= $passingPercentage;
    }

    public function isPerfect(): bool
    {
        return $this->earned === $this->maximum;
    }

    public function add(Score $other): self
    {
        return new self(
            $this->earned + $other->earned,
            $this->maximum + $other->maximum
        );
    }

    public function format(): string
    {
        return sprintf(
            '%.1f/%.1f (%.1f%%)',
            $this->earned,
            $this->maximum,
            $this->getPercentage()
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'earned' => $this->earned,
            'maximum' => $this->maximum,
            'percentage' => $this->getPercentage(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
