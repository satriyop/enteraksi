<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Health Check API', function () {

    describe('GET /api/health/ping', function () {

        it('returns ok status', function () {
            $response = $this->getJson('/api/health/ping');

            $response->assertOk();
            $response->assertJson(['status' => 'ok']);
        });

        it('responds quickly for load balancers', function () {
            $start = microtime(true);

            $response = $this->getJson('/api/health/ping');

            $duration = microtime(true) - $start;

            $response->assertOk();
            expect($duration)->toBeLessThan(0.5); // Should respond in under 500ms
        });
    });

    describe('GET /api/health/check', function () {

        it('returns healthy status when all checks pass', function () {
            // Ensure database is connected (it should be for tests)
            DB::connection()->getPdo();

            $response = $this->getJson('/api/health/check');

            $response->assertOk();
            $response->assertJsonStructure([
                'status',
                'checks' => [
                    'database',
                    'cache',
                ],
                'timestamp',
            ]);
            $response->assertJson(['status' => 'healthy']);
        });

        it('includes database check', function () {
            $response = $this->getJson('/api/health/check');

            $response->assertOk();
            $response->assertJsonPath('checks.database.status', 'healthy');
        });

        it('includes cache check', function () {
            // Set a value in cache to ensure it's working
            Cache::put('health_check_test', 'value', 60);

            $response = $this->getJson('/api/health/check');

            $response->assertOk();
            $response->assertJsonPath('checks.cache.status', 'healthy');
        });

        it('includes timestamp', function () {
            $response = $this->getJson('/api/health/check');

            $response->assertOk();
            $response->assertJsonStructure(['timestamp']);
        });

        it('includes individual check durations', function () {
            $response = $this->getJson('/api/health/check');

            $response->assertOk();

            $data = $response->json();

            foreach ($data['checks'] as $checkName => $check) {
                expect($check)->toHaveKey('duration_ms');
                expect($check['duration_ms'])->toBeGreaterThanOrEqual(0);
            }
        });
    });

    describe('GET /api/health/ready', function () {

        it('returns healthy when database is accessible', function () {
            $response = $this->getJson('/api/health/ready');

            $response->assertOk();
            $response->assertJson(['status' => 'healthy']);
        });

        it('checks database connectivity', function () {
            $response = $this->getJson('/api/health/ready');

            $response->assertOk();
            $response->assertJsonPath('checks.database.status', 'healthy');
        });

        it('returns correct structure for Kubernetes probes', function () {
            $response = $this->getJson('/api/health/ready');

            $response->assertOk();
            $response->assertJsonStructure([
                'status',
                'checks' => [
                    'database' => ['status'],
                ],
                'timestamp',
            ]);
        });
    });

    describe('health check service integration', function () {

        it('correctly identifies unhealthy state', function () {
            // Mock a failing health check by replacing the service
            $this->mock(\App\Domain\Shared\Services\HealthCheckService::class, function ($mock) {
                $mock->shouldReceive('registerDefaultChecks')->andReturnSelf();
                $mock->shouldReceive('addCheck')->andReturnSelf();
                $mock->shouldReceive('runAll')->andReturn([
                    'status' => 'unhealthy',
                    'checks' => [
                        'database' => [
                            'status' => 'fail',
                            'duration_ms' => 0.5,
                        ],
                    ],
                    'timestamp' => now()->toISOString(),
                ]);
            });

            $response = $this->getJson('/api/health/check');

            $response->assertStatus(503);
            $response->assertJson(['status' => 'unhealthy']);
        });
    });
});
