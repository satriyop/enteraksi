<?php

namespace App\Domain\Shared\Exceptions;

use Exception;
use Throwable;

abstract class DomainException extends Exception
{
    protected array $context = [];

    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'code' => $this->getCode(),
        ];
    }
}
