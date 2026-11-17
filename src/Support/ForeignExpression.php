<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * The factory expression for a foreign key. A key that is `id` gets the related factory itself, which Laravel runs when
 * it makes the row. Any other key is read from a row made by a closure, so nothing is created until the factory runs
 * (Blueprint writes `->create()->key` straight into definition(), which creates a row each time definition() is called).
 */
final class ForeignExpression
{
    /**
     * @param  callable(string): string  $name  writes a class name (and imports it)
     */
    public static function make(ForeignTarget $target, callable $name): string
    {
        $factory = $name($target->class) . '::factory()';

        return $target->key === 'id'
            ? $factory
            : 'fn (): mixed => ' . $factory . '->create()->getAttribute(' . ValueGuesser::quote($target->key) . ')';
    }
}
