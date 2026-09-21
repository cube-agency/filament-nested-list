<?php

namespace CubeAgency\FilamentNestedList\Exceptions;

use LogicException;

class ScopeNotSetException extends LogicException
{
    public static function forField(string $statePath): self
    {
        return new self(
            "The nested list [{$statePath}] uses a relationship but no scope has been set. "
            . 'Call `->scope(\'your_foreign_key\')` on the field so the tree can be rebuilt within '
            . 'the correct nested set scope.'
        );
    }
}
