<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * What the guesser decided for one column: the PHP expression for the factory's `definition()`, the classes and
 * members that expression needs, and anything the author of the draft should hear about.
 */
final class Guess
{
    /**
     * @param  list<string>  $imports  fully qualified classes the expression uses
     * @param  array<string, string>  $members  class members it needs (name => declaration), such as a static property
     * @param  list<string>  $notes  warnings, each starting with `Model.column:`
     */
    public function __construct(
        public readonly string $expression,
        public readonly bool $skip = false,
        public readonly array $imports = [],
        public readonly array $members = [],
        public readonly array $notes = [],
        public readonly ?int $pool = null,
    ) {}

    /** @param list<string> $notes */
    public static function skipped(array $notes = []): self
    {
        return new self('', true, notes: $notes);
    }
}
