<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * One generated file, before it is written, with anything the author of the draft should be told about it.
 */
final class Rendered
{
    /**
     * @param  list<string>  $notes  each starting with `Model.column:`
     */
    public function __construct(
        public readonly string $path,
        public readonly string $source,
        public readonly array $notes = [],
        public readonly string $type = 'Factory',
        public readonly bool $guardHandWritten = false,
    ) {}
}
