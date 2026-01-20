<?php

namespace App\Domain\Shared\Services;

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

    /**
     * Format stack trace for logging.
     *
     * @return array<int, array{file: string, line: int, function: string}>
     */
    protected function formatTrace(\Throwable $e): array
    {
        return array_slice(
            array_map(fn ($frame) => [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
            ], $e->getTrace()),
            0,
            5
        );
    }
}
