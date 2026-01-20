<?php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Cache;

class MetricsService
{
    protected string $prefix = 'metrics:';

    /**
     * Increment a counter.
     */
    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $key = $this->buildKey($metric, $tags);
        Cache::increment($key, $value);

        // Also track in time series (hourly buckets)
        $hourlyKey = $key.':'.now()->format('Y-m-d-H');
        Cache::increment($hourlyKey, $value);
    }

    /**
     * Record a timing value.
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $key = $this->buildKey($metric.':timing', $tags);

        // Store in a list for percentile calculations
        $timings = Cache::get($key, []);
        $timings[] = $milliseconds;

        // Keep last 1000 timings
        if (count($timings) > 1000) {
            $timings = array_slice($timings, -1000);
        }

        Cache::put($key, $timings, now()->addHours(24));
    }

    /**
     * Set a gauge value.
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $key = $this->buildKey($metric.':gauge', $tags);
        Cache::put($key, [
            'value' => $value,
            'timestamp' => now()->toISOString(),
        ], now()->addHours(24));
    }

    /**
     * Get metric value.
     */
    public function get(string $metric, array $tags = []): mixed
    {
        $key = $this->buildKey($metric, $tags);

        return Cache::get($key);
    }

    /**
     * Get timing statistics.
     *
     * @return array{count: int, min: float, max: float, avg: float, p50: float, p95: float, p99: float}
     */
    public function getTimingStats(string $metric, array $tags = []): array
    {
        $key = $this->buildKey($metric.':timing', $tags);
        $timings = Cache::get($key, []);

        if (empty($timings)) {
            return [
                'count' => 0,
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'p50' => 0,
                'p95' => 0,
                'p99' => 0,
            ];
        }

        sort($timings);
        $count = count($timings);

        return [
            'count' => $count,
            'min' => min($timings),
            'max' => max($timings),
            'avg' => array_sum($timings) / $count,
            'p50' => $this->percentile($timings, 50),
            'p95' => $this->percentile($timings, 95),
            'p99' => $this->percentile($timings, 99),
        ];
    }

    /**
     * Build cache key from metric name and tags.
     */
    protected function buildKey(string $metric, array $tags): string
    {
        $tagString = '';
        if (! empty($tags)) {
            ksort($tags);
            $tagString = ':'.http_build_query($tags, '', ':');
        }

        return $this->prefix.$metric.$tagString;
    }

    /**
     * Calculate percentile from sorted data.
     *
     * @param  array<float>  $data
     */
    protected function percentile(array $data, int $percentile): float
    {
        $index = ($percentile / 100) * (count($data) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $weight = $index - $lower;

        if ($upper >= count($data)) {
            return $data[count($data) - 1];
        }

        return $data[$lower] * (1 - $weight) + $data[$upper] * $weight;
    }
}
