<?php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;
use App\Models\Question;
use Illuminate\Support\Collection;

class GradingStrategyResolver implements GradingStrategyResolverContract
{
    /** @var Collection<int, GradingStrategyContract> */
    protected Collection $strategies;

    /**
     * @param  iterable<GradingStrategyContract>  $strategies
     */
    public function __construct(iterable $strategies)
    {
        $this->strategies = collect($strategies);
    }

    public function resolve(Question $question): ?GradingStrategyContract
    {
        return $this->strategies->first(
            fn (GradingStrategyContract $strategy) => $strategy->supports($question)
        );
    }

    public function getAllStrategies(): Collection
    {
        return $this->strategies;
    }

    public function getSupportedTypes(): array
    {
        return $this->strategies
            ->flatMap(fn ($strategy) => $strategy->getHandledTypes())
            ->unique()
            ->values()
            ->toArray();
    }
}
