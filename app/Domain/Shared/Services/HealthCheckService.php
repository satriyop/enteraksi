<?php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckService
{
    /** @var array<string, callable> */
    protected array $checks = [];

    /**
     * Add a custom health check.
     */
    public function addCheck(string $name, callable $check): self
    {
        $this->checks[$name] = $check;

        return $this;
    }

    /**
     * Run all registered health checks.
     *
     * @return array{status: string, timestamp: string, checks: array<string, array>}
     */
    public function runAll(): array
    {
        $results = [];
        $allHealthy = true;

        foreach ($this->checks as $name => $check) {
            try {
                $start = microtime(true);
                $result = $check();
                $duration = (microtime(true) - $start) * 1000;

                $results[$name] = [
                    'status' => $result ? 'healthy' : 'unhealthy',
                    'duration_ms' => round($duration, 2),
                ];

                if (! $result) {
                    $allHealthy = false;
                }
            } catch (\Throwable $e) {
                $results[$name] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ];
                $allHealthy = false;
            }
        }

        return [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $results,
        ];
    }

    /**
     * Register default health checks.
     */
    public function registerDefaultChecks(): self
    {
        return $this
            ->addCheck('database', fn () => $this->checkDatabase())
            ->addCheck('cache', fn () => $this->checkCache())
            ->addCheck('queue', fn () => $this->checkQueue())
            ->addCheck('storage', fn () => $this->checkStorage());
    }

    /**
     * Check database connectivity.
     */
    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check cache functionality.
     */
    protected function checkCache(): bool
    {
        try {
            $key = 'health_check_'.time();
            Cache::put($key, true, 10);
            $result = Cache::get($key);
            Cache::forget($key);

            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check queue connectivity.
     */
    protected function checkQueue(): bool
    {
        try {
            // Simple check - queue connection is configured
            return config('queue.default') !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check storage write capability.
     */
    protected function checkStorage(): bool
    {
        try {
            $disk = Storage::disk('local');
            $testFile = 'health_check_'.time().'.txt';
            $disk->put($testFile, 'test');
            $exists = $disk->exists($testFile);
            $disk->delete($testFile);

            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }
}
