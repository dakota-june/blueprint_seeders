<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * PHP source for values: short, single-quoted, one level of array per line.
 */
final class Exporter
{
    public static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => var_export($value, true),
            default => ValueGuesser::quote((string) $value),
        };
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function array(array $values, int $indent = 0, bool $keys = true): string
    {
        if ($values === []) {
            return '[]';
        }

        $pad = str_repeat(' ', $indent + 4);
        $lines = [];

        foreach ($values as $key => $value) {
            $rendered = is_array($value) ? self::array($value, $indent + 4, true) : self::scalar($value);
            $lines[] = $pad . ($keys && ! array_is_list($values) ? self::scalar($key) . ' => ' : '') . $rendered . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat(' ', $indent) . ']';
    }
}
