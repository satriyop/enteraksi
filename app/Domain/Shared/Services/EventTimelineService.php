<?php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventTimelineService
{
    /**
     * Get timeline for a specific aggregate.
     *
     * @return Collection<int, array>
     */
    public function getTimelineForAggregate(string $type, int|string $id): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('aggregate_type', $type)
            ->where('aggregate_id', $id)
            ->orderBy('occurred_at', 'desc')
            ->limit(100)
            ->get())
            ->map(fn ($event) => [
                'id' => $event->id,
                'event_id' => $event->event_id,
                'event_name' => $event->event_name,
                'actor_id' => $event->actor_id,
                'metadata' => json_decode($event->metadata, true),
                'occurred_at' => $event->occurred_at,
            ]);
    }

    /**
     * Get recent events of a specific type.
     *
     * @return Collection<int, array>
     */
    public function getRecentEvents(string $eventName, int $limit = 50): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('event_name', $eventName)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get())
            ->map(fn ($event) => [
                'id' => $event->id,
                'event_id' => $event->event_id,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'actor_id' => $event->actor_id,
                'metadata' => json_decode($event->metadata, true),
                'occurred_at' => $event->occurred_at,
            ]);
    }

    /**
     * Get event statistics for dashboard.
     *
     * @return array{period: string, since: string, total_events: int, by_event: array<string, int>}
     */
    public function getEventStatistics(string $period = '24h'): array
    {
        $since = match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };

        $stats = DB::table('domain_event_log')
            ->select('event_name', DB::raw('COUNT(*) as count'))
            ->where('occurred_at', '>=', $since)
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->get();

        return [
            'period' => $period,
            'since' => $since->toISOString(),
            'total_events' => $stats->sum('count'),
            'by_event' => $stats->pluck('count', 'event_name')->toArray(),
        ];
    }

    /**
     * Get user activity timeline.
     *
     * @return Collection<int, array>
     */
    public function getUserTimeline(int $userId, int $limit = 50): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('actor_id', $userId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get())
            ->map(fn ($event) => [
                'event_name' => $event->event_name,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'metadata' => json_decode($event->metadata, true),
                'occurred_at' => $event->occurred_at,
            ]);
    }

    /**
     * Get events for a specific course.
     *
     * @return Collection<int, array>
     */
    public function getCourseTimeline(int $courseId, int $limit = 100): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('aggregate_type', 'course')
            ->where('aggregate_id', $courseId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get())
            ->map(fn ($event) => [
                'event_name' => $event->event_name,
                'actor_id' => $event->actor_id,
                'metadata' => json_decode($event->metadata, true),
                'occurred_at' => $event->occurred_at,
            ]);
    }

    /**
     * Get events for a specific enrollment.
     *
     * @return Collection<int, array>
     */
    public function getEnrollmentTimeline(int $enrollmentId, int $limit = 100): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('aggregate_type', 'enrollment')
            ->where('aggregate_id', $enrollmentId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get())
            ->map(fn ($event) => [
                'event_name' => $event->event_name,
                'actor_id' => $event->actor_id,
                'metadata' => json_decode($event->metadata, true),
                'occurred_at' => $event->occurred_at,
            ]);
    }
}
