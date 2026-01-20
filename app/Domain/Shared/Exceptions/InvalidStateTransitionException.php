<?php

namespace App\Domain\Shared\Exceptions;

class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $modelType,
        public readonly int|string $modelId,
        ?string $reason = null
    ) {
        $message = sprintf(
            'Cannot transition %s(%s) from "%s" to "%s"',
            $modelType,
            $modelId,
            $from,
            $to
        );

        if ($reason) {
            $message .= ": $reason";
        }

        parent::__construct($message, [
            'from' => $from,
            'to' => $to,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'reason' => $reason,
        ]);
    }
}
