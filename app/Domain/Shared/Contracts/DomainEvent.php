<?php

namespace App\Domain\Shared\Contracts;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class DomainEvent
{
    use Dispatchable, SerializesModels;

    public readonly DateTimeImmutable $occurredAt;

    public readonly ?int $actorId;

    public readonly string $eventId;

    public function __construct(?int $actorId = null)
    {
        $this->occurredAt = new DateTimeImmutable;
        $this->actorId = $actorId ?? auth()->id();
        $this->eventId = (string) Str::uuid();
    }

    /**
     * Get the event name for logging/debugging.
     */
    abstract public function getEventName(): string;

    /**
     * Get event metadata for audit logging.
     *
     * @return array<string, mixed>
     */
    abstract public function getMetadata(): array;

    /**
     * Get the primary entity ID affected by this event.
     */
    abstract public function getAggregateId(): int|string;

    /**
     * Get the aggregate type (e.g., 'course', 'enrollment').
     */
    abstract public function getAggregateType(): string;

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->getEventName(),
            'aggregate_type' => $this->getAggregateType(),
            'aggregate_id' => $this->getAggregateId(),
            'actor_id' => $this->actorId,
            'occurred_at' => $this->occurredAt->format('c'),
            'metadata' => $this->getMetadata(),
        ];
    }
}
