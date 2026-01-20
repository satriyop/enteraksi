<?php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Str;

class LogContext
{
    protected array $context = [];

    protected ?string $requestId = null;

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
            'request_id' => $this->getRequestId(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId() ?? null,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Get a specific context value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Clear all context.
     */
    public function clear(): self
    {
        $this->context = [];

        return $this;
    }

    /**
     * Get or generate request ID.
     */
    public function getRequestId(): string
    {
        if ($this->requestId === null) {
            $this->requestId = request()->header('X-Request-ID', (string) Str::uuid());
        }

        return $this->requestId;
    }
}
