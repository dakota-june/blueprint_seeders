<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\UnifiedDiff;

it('is empty when nothing changed', function () {
    expect(UnifiedDiff::make("a\nb\n", "a\nb\n", 'f.php'))->toBe('');
});

it('shows a changed line with its neighbours', function () {
    $diff = UnifiedDiff::make("a\nb\nc\n", "a\nB\nc\n", 'f.php');

    expect($diff)->toBe("--- f.php\n+++ f.php\n@@ -1,3 +1,3 @@\n a\n-b\n+B\n c\n");
});

it('shows an added and a removed line', function () {
    expect(UnifiedDiff::make("a\n", "a\nb\n", 'f'))->toContain("+b\n")
        ->and(UnifiedDiff::make("a\nb\n", "a\n", 'f'))->toContain("-b\n");
});

it('treats a new file as all additions', function () {
    $diff = UnifiedDiff::make('', "x\ny\n", 'f.php');

    expect($diff)->toContain('@@ -0,0 +1,2 @@')->toContain("+x\n+y\n");
});

it('keeps distant changes in separate hunks, with three lines of context', function () {
    $old = implode("\n", range(1, 20)) . "\n";
    $new = str_replace(["\n2\n", "\n19\n"], ["\ntwo\n", "\nnineteen\n"], $old);
    $diff = UnifiedDiff::make($old, $new, 'f');

    expect(substr_count($diff, '@@ -'))->toBe(2)
        ->and($diff)->toContain('-2')->toContain('+two')->toContain('-19')->toContain('+nineteen')->not->toContain(' 10');
});

it('does not care about the line endings of either side', function () {
    expect(UnifiedDiff::make("a\r\nb\r\n", "a\nb\n", 'f'))->toBe('');
});
