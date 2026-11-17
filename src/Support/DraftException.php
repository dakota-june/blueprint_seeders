<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use InvalidArgumentException;

/**
 * A problem in the draft, worded as `Model.column: what is wrong. What to do.` so it names the thing to fix.
 */
final class DraftException extends InvalidArgumentException
{
    public static function at(string $where, string $problem, ?string $hint = null): self
    {
        return new self($where . ': ' . $problem . ($hint === null ? '' : ' ' . $hint));
    }

    public static function column(string $model, string $column, string $problem, ?string $hint = null): self
    {
        return self::at($model . '.' . $column, $problem, $hint);
    }
}
