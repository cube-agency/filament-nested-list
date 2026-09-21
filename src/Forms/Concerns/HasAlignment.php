<?php

namespace CubeAgency\FilamentNestedList\Forms\Concerns;

use Closure;
use Filament\Support\Enums\Alignment;

trait HasAlignment
{
    protected Alignment|string|Closure|null $addActionAlignment = Alignment::End;

    public function addActionAlignment(Alignment|string|Closure|null $addActionAlignment): static
    {
        $this->addActionAlignment = $addActionAlignment;

        return $this;
    }

    /** Always resolves to an `Alignment`, so the view never has to handle a raw string. */
    public function getAddActionAlignment(): Alignment
    {
        $alignment = $this->evaluate($this->addActionAlignment);

        if (is_string($alignment)) {
            $alignment = Alignment::tryFrom($alignment);
        }

        return $alignment ?? Alignment::Center;
    }
}
