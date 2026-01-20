<?php

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
