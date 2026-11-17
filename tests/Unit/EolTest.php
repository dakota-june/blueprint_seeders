<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\Eol;

it('converts to one kind of line ending, whatever it was given', function () {
    // \r\r\n is what code produces when a CRLF stub meets PHP_EOL on Windows.
    $mixed = "a\nb\r\nc\r\r\nd";

    expect(Eol::to($mixed, "\r\n"))->toBe("a\r\nb\r\nc\r\nd")
        ->and(Eol::to($mixed, "\n"))->toBe("a\nb\nc\nd")
        ->and(Eol::lf($mixed))->toBe("a\nb\nc\nd");
});

it('hashes the same text the same way on every platform', function () {
    expect(Eol::hash("a\r\nb\r\n"))->toBe(Eol::hash("a\nb\n"));
});
