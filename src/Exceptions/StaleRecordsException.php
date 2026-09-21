<?php

namespace CubeAgency\FilamentNestedList\Exceptions;

use RuntimeException;

class StaleRecordsException extends RuntimeException
{
    /**
     * @param  array<int, mixed>  $keys
     */
    public static function forField(string $statePath, array $keys): self
    {
        $keys = implode(', ', array_map(
            static fn (mixed $key): string => is_scalar($key) ? (string) $key : get_debug_type($key),
            $keys,
        ));

        return new self(
            "The nested list [{$statePath}] holds records that are no longer within its scope: [{$keys}]. "
            . 'They were most likely deleted after this form was loaded. Nothing has been saved — reload '
            . 'the page to start from the current tree.'
        );
    }
}
