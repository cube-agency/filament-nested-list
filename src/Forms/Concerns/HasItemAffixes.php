<?php

namespace CubeAgency\FilamentNestedList\Forms\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Stringable;

trait HasItemAffixes
{
    protected string|Htmlable|Closure|null $itemPrefix = null;

    protected string|Htmlable|Closure|null $itemSuffix = null;

    public function itemPrefix(string|Htmlable|Closure|null $prefix): static
    {
        $this->itemPrefix = $prefix;

        return $this;
    }

    public function itemSuffix(string|Htmlable|Closure|null $suffix): static
    {
        $this->itemSuffix = $suffix;

        return $this;
    }

    /** Rendered before an item's label. A closure receives that item's state as `$item`. */
    public function getItemPrefix(mixed $item): string|Htmlable|null
    {
        return $this->normalizeItemAffix($this->evaluate($this->itemPrefix, ['item' => $item]));
    }

    /** Rendered after an item's label, ahead of its actions. */
    public function getItemSuffix(mixed $item): string|Htmlable|null
    {
        return $this->normalizeItemAffix($this->evaluate($this->itemSuffix, ['item' => $item]));
    }

    /** A string is escaped on render and an `Htmlable` is not. Anything else renders as nothing. */
    protected function normalizeItemAffix(mixed $affix): string|Htmlable|null
    {
        if ($affix instanceof Htmlable) {
            return $affix;
        }

        if (is_scalar($affix) || $affix instanceof Stringable) {
            return (string) $affix;
        }

        return null;
    }
}
