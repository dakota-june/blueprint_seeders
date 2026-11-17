<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * A number of rows: `20`, or a range `1..3` that is drawn again for every parent.
 */
final class CountSpec
{
    public function __construct(public readonly int $min, public readonly int $max) {}

    public static function parse(mixed $value, string $where): self
    {
        if (is_int($value) && $value >= 0) {
            return new self($value, $value);
        }

        if (is_string($value) && preg_match('/^\s*(\d+)\s*(?:\.\.\s*(\d+))?\s*$/', $value, $m) === 1) {
            $min = (int) $m[1];
            $max = isset($m[2]) ? (int) $m[2] : $min;

            if ($min <= $max) {
                return new self($min, $max);
            }

            throw DraftException::at($where, "the range \"{$value}\" runs backwards.", 'Write the smaller number first, like 1..3.');
        }

        $shown = is_scalar($value) ? var_export($value, true) : gettype($value);

        throw DraftException::at($where, "expected a whole number or a range like 1..3, got {$shown}.");
    }

    public function isFixed(): bool
    {
        return $this->min === $this->max;
    }
}
