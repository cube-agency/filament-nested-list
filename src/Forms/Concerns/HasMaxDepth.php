<?php

namespace CubeAgency\FilamentNestedList\Forms\Concerns;

use Closure;

trait HasMaxDepth
{
    protected int|Closure|null $maxDepth = null;

    public function maxDepth(int|Closure|null $maxDepth): static
    {
        $this->maxDepth = $maxDepth;

        return $this;
    }

    public function getMaxDepth(): ?int
    {
        // Null-coalesced, so `maxDepth(0)` is honoured rather than falling back to the config value.
        return $this->evaluate($this->maxDepth) ?? config('filament-nested-list.max_depth');
    }

    public function getDeepestAllowedDepth(): int
    {
        return max(0, (int) $this->getMaxDepth());
    }
}
