<?php

namespace App\Domain\Shared\Listeners;

use App\Domain\Shared\Contracts\DomainEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogDomainEvent implements ShouldQueue
{
    public string $queue = 'audit';

    public function handle(DomainEvent $event): void
    {
        // Log to database
        DB::table('domain_event_log')->insert([
            'event_id' => $event->eventId,
            'event_name' => $event->getEventName(),
            'aggregate_type' => $event->getAggregateType(),
            'aggregate_id' => $event->getAggregateId(),
            'actor_id' => $event->actorId,
            'metadata' => json_encode($event->getMetadata()),
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s'),
            'created_at' => now(),
        ]);

        // Also log to file for debugging
        Log::channel('single')->info($event->getEventName(), $event->toArray());
    }
}
