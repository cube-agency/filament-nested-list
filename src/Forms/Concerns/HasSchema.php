<?php

namespace CubeAgency\FilamentNestedList\Forms\Concerns;

use Closure;
use Filament\Schemas\Schema;

/**
 * Holds the schema used for each item's add/edit modal.
 *
 * `schema()` deliberately shadows `HasChildComponents::schema()`: these components belong to the
 * item action modals, not to the field itself.
 */
trait HasSchema
{
    /**
     * @var array<mixed>|Closure
     */
    protected array|Closure $components = [];

    /**
     * @param  array<mixed>|Schema|Closure|null  $components
     */
    public function schema(array|Schema|Closure|null $components): static
    {
        $this->components($components);

        return $this;
    }

    /**
     * @param  array<mixed>|Schema|Closure|null  $components
     */
    public function components(array|Schema|Closure|null $components): static
    {
        $this->components = $components instanceof Schema
            ? fn (): array => $components->getComponents()
            : $components ?? [];

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getItemSchema(): array
    {
        return $this->evaluate($this->components) ?? [];
    }
}
