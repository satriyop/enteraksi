<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Trait for models that have computed attributes requiring eager loading.
 *
 * This trait helps prevent N+1 query issues by:
 * - Throwing exceptions in development when eager loading is missing
 * - Logging warnings in production for graceful degradation
 *
 * Usage in model:
 * ```php
 * use RequiresEagerLoading;
 *
 * public function getTotalLessonsAttribute(): int
 * {
 *     return $this->getEagerCount('lessons');
 * }
 * ```
 */
trait RequiresEagerLoading
{
    /**
     * Get an eagerly loaded count or handle missing eager load.
     *
     * @param  string  $relation  The relation name (e.g., 'lessons')
     * @param  string|null  $attribute  Override attribute name (default: {relation}_count)
     */
    protected function getEagerCount(string $relation, ?string $attribute = null): int
    {
        $attribute ??= Str::snake($relation).'_count';

        if (array_key_exists($attribute, $this->attributes)) {
            return (int) $this->attributes[$attribute];
        }

        return (int) $this->handleMissingEagerLoad(
            suggestion: "withCount('{$relation}')",
            attributeName: $attribute,
            fallback: fn () => $this->{$relation}()->count()
        );
    }

    /**
     * Get an eagerly loaded average or handle missing eager load.
     *
     * @param  string  $relation  The relation name (e.g., 'ratings')
     * @param  string  $column  The column to average (e.g., 'rating')
     * @param  string|null  $attribute  Override attribute name (default: {relation}_avg_{column})
     */
    protected function getEagerAvg(string $relation, string $column, ?string $attribute = null): ?float
    {
        $attribute ??= Str::snake($relation).'_avg_'.$column;

        if (array_key_exists($attribute, $this->attributes)) {
            $value = $this->attributes[$attribute];

            return $value !== null ? (float) $value : null;
        }

        return $this->handleMissingEagerLoad(
            suggestion: "withAvg('{$relation}', '{$column}')",
            attributeName: $attribute,
            fallback: fn () => $this->{$relation}()->avg($column)
        );
    }

    /**
     * Get an eagerly loaded sum or handle missing eager load.
     *
     * @param  string  $relation  The relation name (e.g., 'items')
     * @param  string  $column  The column to sum (e.g., 'amount')
     * @param  string|null  $attribute  Override attribute name (default: {relation}_sum_{column})
     */
    protected function getEagerSum(string $relation, string $column, ?string $attribute = null): float
    {
        $attribute ??= Str::snake($relation).'_sum_'.$column;

        if (array_key_exists($attribute, $this->attributes)) {
            return (float) ($this->attributes[$attribute] ?? 0);
        }

        return (float) $this->handleMissingEagerLoad(
            suggestion: "withSum('{$relation}', '{$column}')",
            attributeName: $attribute,
            fallback: fn () => $this->{$relation}()->sum($column)
        );
    }

    /**
     * Handle missing eager load - throw in dev, warn in production.
     *
     * @param  string  $suggestion  The method suggestion (e.g., "withCount('lessons')")
     * @param  string  $attributeName  The expected attribute name
     * @param  callable  $fallback  The fallback query to execute
     * @return mixed The result from fallback query
     *
     * @throws \RuntimeException In local/testing environments
     */
    private function handleMissingEagerLoad(string $suggestion, string $attributeName, callable $fallback): mixed
    {
        $class = static::class;
        $message = "N+1 query detected: {$class}::{$attributeName} accessed without {$suggestion}. "
            ."Add ->{$suggestion} to your query to fix this.";

        // In development/testing: fail fast to catch issues early
        if (app()->environment('local', 'testing')) {
            throw new \RuntimeException($message);
        }

        // In production: log warning and fallback gracefully
        Log::warning($message, [
            'model' => $class,
            'model_id' => $this->getKey(),
            'attribute' => $attributeName,
            'suggestion' => $suggestion,
            'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))
                ->map(fn ($frame) => ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'()')
                ->filter()
                ->take(5)
                ->values()
                ->toArray(),
        ]);

        return $fallback();
    }
}
