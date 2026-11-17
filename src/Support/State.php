<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * A named state of a factory: `Contact::factory()->vip()`.
 */
final class State
{
    /**
     * @param  array<string, string>  $attributes  column => PHP expression
     */
    public function __construct(public readonly string $name, public readonly array $attributes, public readonly string $doc) {}
}
