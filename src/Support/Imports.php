<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Illuminate\Support\Str;

/**
 * The `use` lines of one generated file. A class is written by its short name when that is free; when two classes would
 * share one name, the second is written in full, so a file never imports two things under the same name.
 */
final class Imports
{
    /** @var array<string, string> short name => class */
    private array $short = [];

    public function name(string $class): string
    {
        $class = ltrim($class, '\\');

        if (! str_contains($class, '\\')) {
            return $class;
        }

        $short = Str::afterLast($class, '\\');

        if (($this->short[$short] ?? $class) !== $class) {
            return '\\' . $class;
        }

        $this->short[$short] = $class;

        return $short;
    }

    /** @return list<string> */
    public function lines(): array
    {
        $classes = array_values($this->short);
        sort($classes, SORT_STRING);

        return array_map(fn (string $class): string => "use {$class};", $classes);
    }
}
