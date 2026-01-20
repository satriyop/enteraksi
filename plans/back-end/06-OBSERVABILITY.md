# Phase 6: Observability & Debugging

**Duration**: Week 12
**Dependencies**: Phase 5 complete
**Priority**: Medium - Essential for production readiness

---

## Objectives

1. Implement structured logging across all services
2. Create health check endpoints
3. Add metrics collection points
4. Enhance error context for debugging
5. Build admin dashboard for event monitoring

---

## 6.1 Why Observability?

### Current Problems

```php
// Current: Minimal logging, hard to debug
public function submitAttempt(...)
{
    // No logging of what's happening
    // If something fails, good luck figuring out why
    $attempt->update([...]);
    return redirect();
}

// Problems:
// 1. No visibility into system state
// 2. Hard to debug production issues
// 3. No performance monitoring
// 4. Can't track user journeys
```

### With Observability

```php
// After: Rich logging and monitoring
public function submitAttempt(...)
{
    $this->logger->info('assessment.submission.started', [
        'attempt_id' => $attempt->id,
        'user_id' => $user->id,
    ]);

    // Process submission...

    $this->logger->info('assessment.submission.completed', [
        'attempt_id' => $attempt->id,
        'duration_ms' => $timer->elapsed(),
        'status' => $result->status,
    ]);
}

// Benefits:
// 1. Clear visibility into system behavior
// 2. Easy production debugging
// 3. Performance tracking
// 4. User journey tracing
```

---

## 6.2 Structured Logging

### Log Context Service

```php
<?php
// app/Domain/Shared/Services/LogContext.php

namespace App\Domain\Shared\Services;

class LogContext
{
    protected array $context = [];

    /**
     * Set context that will be included in all subsequent logs.
     */
    public function set(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Set multiple context values.
     */
    public function setMany(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Get all context.
     */
    public function all(): array
    {
        return array_merge($this->context, [
            'request_id' => request()->header('X-Request-ID', $this->generateRequestId()),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Clear all context.
     */
    public function clear(): self
    {
        $this->context = [];
        return $this;
    }

    protected function generateRequestId(): string
    {
        static $requestId = null;

        if ($requestId === null) {
            $requestId = (string) \Illuminate\Support\Str::uuid();
        }

        return $requestId;
    }
}
```

### Domain Logger

```php
<?php
// app/Domain/Shared/Services/DomainLogger.php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class DomainLogger
{
    public function __construct(
        protected LogContext $context,
        protected LoggerInterface $logger
    ) {}

    /**
     * Log domain action started.
     */
    public function actionStarted(string $action, array $data = []): void
    {
        $this->info("{$action}.started", $data);
    }

    /**
     * Log domain action completed successfully.
     */
    public function actionCompleted(string $action, array $data = []): void
    {
        $this->info("{$action}.completed", $data);
    }

    /**
     * Log domain action failed.
     */
    public function actionFailed(string $action, \Throwable $e, array $data = []): void
    {
        $this->error("{$action}.failed", array_merge($data, [
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'trace' => $this->formatTrace($e),
        ]));
    }

    /**
     * Log with info level.
     */
    public function info(string $message, array $data = []): void
    {
        $this->log('info', $message, $data);
    }

    /**
     * Log with warning level.
     */
    public function warning(string $message, array $data = []): void
    {
        $this->log('warning', $message, $data);
    }

    /**
     * Log with error level.
     */
    public function error(string $message, array $data = []): void
    {
        $this->log('error', $message, $data);
    }

    /**
     * Log with debug level.
     */
    public function debug(string $message, array $data = []): void
    {
        if (config('app.debug')) {
            $this->log('debug', $message, $data);
        }
    }

    /**
     * Core log method.
     */
    protected function log(string $level, string $message, array $data): void
    {
        $context = array_merge($this->context->all(), $data, [
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->{$level}($message, $context);
    }

    protected function formatTrace(\Throwable $e): array
    {
        return array_slice(
            array_map(fn($frame) => [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
            ], $e->getTrace()),
            0,
            5
        );
    }
}
```

### Logging Middleware

```php
<?php
// app/Http/Middleware/LogRequestContext.php

namespace App\Http\Middleware;

use App\Domain\Shared\Services\LogContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestContext
{
    public function __construct(
        protected LogContext $logContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Set request context for logging
        $this->logContext->setMany([
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->logContext->clear();
    }
}
```

---

## 6.3 Service Logging Integration

### Updated EnrollmentService with Logging

```php
<?php
// app/Domain/Enrollment/Services/EnrollmentService.php (with logging)

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\DTOs\EnrollmentResult;
use App\Domain\Enrollment\Exceptions\AlreadyEnrolledException;
use App\Domain\Shared\Services\DomainLogger;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentService implements EnrollmentServiceContract
{
    public function __construct(
        protected DomainLogger $logger
    ) {}

    public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult
    {
        $this->logger->actionStarted('enrollment.create', [
            'user_id' => $dto->userId,
            'course_id' => $dto->courseId,
        ]);

        try {
            $user = User::findOrFail($dto->userId);
            $course = Course::findOrFail($dto->courseId);

            $this->validateEnrollment($user, $course);

            $result = DB::transaction(function () use ($dto, $user, $course) {
                $enrollment = Enrollment::create([
                    'user_id' => $dto->userId,
                    'course_id' => $dto->courseId,
                    'status' => 'active',
                    'progress_percentage' => 0,
                    'enrolled_at' => $dto->enrolledAt ?? now(),
                    'invited_by' => $dto->invitedBy,
                ]);

                return new EnrollmentResult(
                    enrollment: $enrollment,
                    isNewEnrollment: true,
                );
            });

            $this->logger->actionCompleted('enrollment.create', [
                'enrollment_id' => $result->enrollment->id,
                'user_id' => $dto->userId,
                'course_id' => $dto->courseId,
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->logger->actionFailed('enrollment.create', $e, [
                'user_id' => $dto->userId,
                'course_id' => $dto->courseId,
            ]);

            throw $e;
        }
    }

    // ... other methods with similar logging pattern
}
```

---

## 6.4 Health Checks

### Health Check Service

```php
<?php
// app/Domain/Shared/Services/HealthCheckService.php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckService
{
    protected array $checks = [];

    public function addCheck(string $name, callable $check): self
    {
        $this->checks[$name] = $check;
        return $this;
    }

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

                if (!$result) {
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

    public function registerDefaultChecks(): self
    {
        return $this
            ->addCheck('database', fn() => $this->checkDatabase())
            ->addCheck('cache', fn() => $this->checkCache())
            ->addCheck('queue', fn() => $this->checkQueue())
            ->addCheck('storage', fn() => $this->checkStorage());
    }

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

    protected function checkCache(): bool
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, true, 10);
            $result = Cache::get($key);
            Cache::forget($key);
            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function checkQueue(): bool
    {
        try {
            // Simple check - queue connection is available
            return config('queue.default') !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function checkStorage(): bool
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $testFile = 'health_check_' . time() . '.txt';
            $disk->put($testFile, 'test');
            $exists = $disk->exists($testFile);
            $disk->delete($testFile);
            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### Health Check Controller

```php
<?php
// app/Http/Controllers/Api/HealthController.php

namespace App\Http\Controllers\Api;

use App\Domain\Shared\Services\HealthCheckService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        protected HealthCheckService $healthCheck
    ) {}

    /**
     * Basic health check (for load balancers).
     */
    public function ping(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Detailed health check.
     */
    public function check(): JsonResponse
    {
        $results = $this->healthCheck
            ->registerDefaultChecks()
            ->runAll();

        $statusCode = $results['status'] === 'healthy' ? 200 : 503;

        return response()->json($results, $statusCode);
    }

    /**
     * Readiness check (for Kubernetes).
     */
    public function ready(): JsonResponse
    {
        $results = $this->healthCheck
            ->addCheck('database', fn() => $this->checkDatabase())
            ->runAll();

        $statusCode = $results['status'] === 'healthy' ? 200 : 503;

        return response()->json($results, $statusCode);
    }

    protected function checkDatabase(): bool
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### Health Check Routes

```php
<?php
// routes/api.php (add these routes)

use App\Http\Controllers\Api\HealthController;

Route::prefix('health')->group(function () {
    Route::get('/ping', [HealthController::class, 'ping']);
    Route::get('/check', [HealthController::class, 'check']);
    Route::get('/ready', [HealthController::class, 'ready']);
});
```

---

## 6.5 Metrics Collection

### Metrics Service

```php
<?php
// app/Domain/Shared/Services/MetricsService.php

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
        $hourlyKey = $key . ':' . now()->format('Y-m-d-H');
        Cache::increment($hourlyKey, $value);
    }

    /**
     * Record a timing value.
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $key = $this->buildKey($metric . ':timing', $tags);

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
        $key = $this->buildKey($metric . ':gauge', $tags);
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
     */
    public function getTimingStats(string $metric, array $tags = []): array
    {
        $key = $this->buildKey($metric . ':timing', $tags);
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

    protected function buildKey(string $metric, array $tags): string
    {
        $tagString = '';
        if (!empty($tags)) {
            ksort($tags);
            $tagString = ':' . http_build_query($tags, '', ':');
        }

        return $this->prefix . $metric . $tagString;
    }

    protected function percentile(array $data, int $percentile): float
    {
        $index = ($percentile / 100) * (count($data) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($upper >= count($data)) {
            return $data[count($data) - 1];
        }

        return $data[$lower] * (1 - $weight) + $data[$upper] * $weight;
    }
}
```

### Metrics Middleware

```php
<?php
// app/Http/Middleware/CollectMetrics.php

namespace App\Http\Middleware;

use App\Domain\Shared\Services\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CollectMetrics
{
    public function __construct(
        protected MetricsService $metrics
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $start) * 1000;
        $route = $request->route()?->getName() ?? 'unknown';

        // Record request timing
        $this->metrics->timing('http.request.duration', $duration, [
            'route' => $route,
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
        ]);

        // Increment request counter
        $this->metrics->increment('http.request.count', 1, [
            'route' => $route,
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
```

---

## 6.6 Error Context Enrichment

### Enhanced Exception Handler

```php
<?php
// app/Exceptions/Handler.php (or bootstrap/app.php in Laravel 11+)

namespace App\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Services\DomainLogger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Report the exception.
     */
    public function report(Throwable $e): void
    {
        if ($this->shouldReport($e)) {
            $this->logWithContext($e);
        }

        parent::report($e);
    }

    /**
     * Render the exception.
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof DomainException && $request->expectsJson()) {
            return response()->json([
                'error' => $e->getMessage(),
                'context' => config('app.debug') ? $e->getContext() : null,
                'code' => $e->getCode(),
            ], $this->getStatusCode($e));
        }

        return parent::render($request, $e);
    }

    protected function logWithContext(Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
            'input' => $this->sanitizeInput(request()->all()),
        ];

        if ($e instanceof DomainException) {
            $context['domain_context'] = $e->getContext();
        }

        app(DomainLogger::class)->error('exception.occurred', $context);
    }

    protected function sanitizeInput(array $input): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

        foreach ($sensitive as $key) {
            if (isset($input[$key])) {
                $input[$key] = '[REDACTED]';
            }
        }

        return $input;
    }

    protected function getStatusCode(Throwable $e): int
    {
        return match (true) {
            $e instanceof \App\Domain\Shared\Exceptions\ValidationException => 422,
            $e instanceof \App\Domain\Shared\Exceptions\InvalidStateTransitionException => 409,
            $e instanceof \App\Domain\Enrollment\Exceptions\AlreadyEnrolledException => 409,
            $e instanceof \App\Domain\Assessment\Exceptions\MaxAttemptsReachedException => 403,
            default => 500,
        };
    }
}
```

---

## 6.7 Event Timeline Dashboard

### Event Query Service

```php
<?php
// app/Domain/Shared/Services/EventTimelineService.php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventTimelineService
{
    /**
     * Get timeline for a specific aggregate.
     */
    public function getTimelineForAggregate(string $type, int|string $id): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('aggregate_type', $type)
            ->where('aggregate_id', $id)
            ->orderBy('occurred_at', 'desc')
            ->limit(100)
            ->get())
            ->map(fn($event) => [
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
     */
    public function getRecentEvents(string $eventName, int $limit = 50): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('event_name', $eventName)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get())
            ->map(fn($event) => [
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
     */
    public function getUserTimeline(int $userId, int $limit = 50): Collection
    {
        return collect(DB::table('domain_event_log')
            ->where('actor_id', $userId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get())
            ->map(fn($event) => [
                'event_name' => $event->event_name,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'metadata' => json_decode($event->metadata, true),
                'occurred_at' => $event->occurred_at,
            ]);
    }
}
```

---

## 6.8 Debug Mode Enhancements

### Debug Bar Integration

```php
<?php
// app/Providers/DebugServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class DebugServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!config('app.debug')) {
            return;
        }

        // Log all domain events in debug mode
        Event::listen('*', function (string $eventName, array $data) {
            if (str_starts_with($eventName, 'App\\Domain\\')) {
                \Illuminate\Support\Facades\Log::channel('debug')->debug($eventName, [
                    'event_class' => $eventName,
                    'data' => $this->summarizeEventData($data),
                ]);
            }
        });
    }

    protected function summarizeEventData(array $data): array
    {
        return array_map(function ($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            if (is_object($item)) {
                return get_class($item);
            }
            return $item;
        }, $data);
    }
}
```

### Query Logging for Development

```php
<?php
// app/Providers/AppServiceProvider.php (add to boot method)

public function boot(): void
{
    if (config('app.debug') && config('logging.query_logging', false)) {
        \Illuminate\Support\Facades\DB::listen(function ($query) {
            \Illuminate\Support\Facades\Log::channel('queries')->debug($query->sql, [
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        });
    }
}
```

---

## 6.9 Logging Configuration

### Logging Channels

```php
<?php
// config/logging.php (add these channels)

return [
    'channels' => [
        // ... existing channels

        'domain' => [
            'driver' => 'daily',
            'path' => storage_path('logs/domain.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'events' => [
            'driver' => 'daily',
            'path' => storage_path('logs/events.log'),
            'level' => 'info',
            'days' => 30,
        ],

        'debug' => [
            'driver' => 'daily',
            'path' => storage_path('logs/debug.log'),
            'level' => 'debug',
            'days' => 7,
        ],

        'queries' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queries.log'),
            'level' => 'debug',
            'days' => 3,
        ],

        'metrics' => [
            'driver' => 'daily',
            'path' => storage_path('logs/metrics.log'),
            'level' => 'info',
            'days' => 30,
        ],
    ],
];
```

---

## 6.10 Implementation Checklist

### Week 12

- [ ] Structured Logging
  - [ ] Create LogContext service
  - [ ] Create DomainLogger service
  - [ ] Create LogRequestContext middleware
  - [ ] Integrate logging into services

- [ ] Health Checks
  - [ ] Create HealthCheckService
  - [ ] Create HealthController
  - [ ] Add health check routes
  - [ ] Test health endpoints

- [ ] Metrics
  - [ ] Create MetricsService
  - [ ] Create CollectMetrics middleware
  - [ ] Add key metrics collection points

- [ ] Error Handling
  - [ ] Enhance exception handler
  - [ ] Add domain-specific error codes
  - [ ] Add context to all exceptions

- [ ] Event Timeline
  - [ ] Create EventTimelineService
  - [ ] Add admin endpoints for event viewing

- [ ] Configuration
  - [ ] Add logging channels
  - [ ] Configure debug mode features
  - [ ] Document logging conventions

---

## Next Phase

Once Phase 6 is complete, proceed to [Phase 7: Testing Strategy](./07-TESTING-STRATEGY.md).

The system is now observable and debuggable. Phase 7 ensures all the new architecture is thoroughly tested.
