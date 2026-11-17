<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * A unified diff of two texts, in plain PHP: the machine running `seeders:preview` may be a Windows one with no `diff`.
 * Files here are a few hundred lines at most, so the longest-common-subsequence table is cheap.
 */
final class UnifiedDiff
{
    private const CONTEXT = 3;

    public static function make(string $old, string $new, string $path): string
    {
        $a = self::lines($old);
        $b = self::lines($new);

        if ($a === $b) {
            return '';
        }

        $ops = self::operations($a, $b);
        $hunks = self::hunks($ops);
        $out = "--- {$path}\n+++ {$path}\n";

        foreach ($hunks as [$from, $to]) {
            $slice = array_slice($ops, $from, $to - $from + 1);
            $oldStart = $slice[0]['old'];
            $newStart = $slice[0]['new'];
            $oldCount = count(array_filter($slice, fn (array $op): bool => $op['op'] !== '+'));
            $newCount = count(array_filter($slice, fn (array $op): bool => $op['op'] !== '-'));

            $out .= sprintf("@@ -%s +%s @@\n", self::range($oldStart, $oldCount), self::range($newStart, $newCount));

            foreach ($slice as $op) {
                $out .= ($op['op'] === '=' ? ' ' : $op['op']) . $op['text'] . "\n";
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function lines(string $text): array
    {
        $text = Eol::lf($text);

        return $text === '' ? [] : explode("\n", rtrim($text, "\n"));
    }

    private static function range(int $start, int $count): string
    {
        return $count === 0 ? ($start - 1) . ',0' : ($count === 1 ? (string) $start : "{$start},{$count}");
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{op: string, text: string, old: int, new: int}> old and new are the 1-based line numbers each op sits at
     */
    private static function operations(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = $j = 0;

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $ops[] = ['op' => '=', 'text' => $a[$i], 'old' => $i + 1, 'new' => $j + 1];
                $i++;
                $j++;
            } elseif ($i < $n && ($j === $m || $table[$i + 1][$j] >= $table[$i][$j + 1])) {
                $ops[] = ['op' => '-', 'text' => $a[$i], 'old' => $i + 1, 'new' => $j + 1];
                $i++;
            } else {
                $ops[] = ['op' => '+', 'text' => $b[$j], 'old' => $i + 1, 'new' => $j + 1];
                $j++;
            }
        }

        return $ops;
    }

    /**
     * @param  list<array{op: string, text: string, old: int, new: int}>  $ops
     * @return list<array{0: int, 1: int}> first and last index of each hunk
     */
    private static function hunks(array $ops): array
    {
        $changed = array_keys(array_filter($ops, fn (array $op): bool => $op['op'] !== '='));
        $hunks = [];

        foreach ($changed as $index) {
            $from = max(0, $index - self::CONTEXT);
            $to = min(count($ops) - 1, $index + self::CONTEXT);

            if ($hunks !== [] && $from <= $hunks[count($hunks) - 1][1] + 1) {
                $hunks[count($hunks) - 1][1] = $to;
            } else {
                $hunks[] = [$from, $to];
            }
        }

        return $hunks;
    }
}
