<?php

namespace App\Http\Controllers\Api;

use App\Domain\Shared\Services\HealthCheckService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
            ->addCheck('database', fn () => $this->checkDatabase())
            ->runAll();

        $statusCode = $results['status'] === 'healthy' ? 200 : 503;

        return response()->json($results, $statusCode);
    }

    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
