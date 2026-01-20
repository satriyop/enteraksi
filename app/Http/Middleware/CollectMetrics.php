<?php

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
