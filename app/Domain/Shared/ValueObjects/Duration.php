<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Duration implements JsonSerializable, Stringable
{
    private const SECONDS_PER_MINUTE = 60;

    private const SECONDS_PER_HOUR = 3600;

    public function __construct(
        public int $seconds
    ) {
        if ($seconds < 0) {
            throw new InvalidArgumentException(
                "Duration cannot be negative, got: {$seconds}"
            );
        }
    }

    public static function fromMinutes(int $minutes): self
    {
        return new self($minutes * self::SECONDS_PER_MINUTE);
    }

    public static function fromHours(int $hours): self
    {
        return new self($hours * self::SECONDS_PER_HOUR);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function toMinutes(): int
    {
        return (int) floor($this->seconds / self::SECONDS_PER_MINUTE);
    }

    public function toHours(): float
    {
        return $this->seconds / self::SECONDS_PER_HOUR;
    }

    public function add(Duration $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    public function format(): string
    {
        if ($this->seconds < self::SECONDS_PER_MINUTE) {
            return "{$this->seconds} detik";
        }

        $minutes = $this->toMinutes();

        if ($minutes < 60) {
            return "{$minutes} menit";
        }

        $hours = (int) floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return "{$hours} jam";
        }

        return "{$hours} jam {$remainingMinutes} menit";
    }

    public function formatShort(): string
    {
        $hours = (int) floor($this->seconds / self::SECONDS_PER_HOUR);
        $minutes = (int) floor(($this->seconds % self::SECONDS_PER_HOUR) / self::SECONDS_PER_MINUTE);
        $seconds = $this->seconds % self::SECONDS_PER_MINUTE;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function jsonSerialize(): int
    {
        return $this->seconds;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
