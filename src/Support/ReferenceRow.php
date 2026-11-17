<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * One fixed row of reference data. `key` is the value of the match column when the draft gave it as a list item or as a
 * map key; it is null when the row spells out its own columns.
 */
final class ReferenceRow
{
    /**
     * @param  array<string, scalar|null>  $attributes
     */
    public function __construct(public readonly mixed $key, public readonly array $attributes = []) {}
}
