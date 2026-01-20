<?php

namespace App\Domain\Shared\Contracts;

interface StrategyContract
{
    /**
     * Check if this strategy can handle the given context.
     */
    public function supports(mixed $context): bool;
}
