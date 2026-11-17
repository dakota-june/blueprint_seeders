<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * One kind of line ending, whatever the input had. Generated files use PHP_EOL like Blueprint's (so \r\n on Windows);
 * hashes are taken over the \n form, so a file checked out with another line ending is still recognised as unchanged.
 */
final class Eol
{
    public static function lf(string $text): string
    {
        // \r\r\n is what code produces when a CRLF stub meets PHP_EOL, hence `\r*`.
        return (string) preg_replace('/\r*\n/', "\n", $text);
    }

    public static function to(string $text, string $eol = PHP_EOL): string
    {
        return str_replace("\n", $eol, self::lf($text));
    }

    public static function hash(string $text): string
    {
        return sha1(self::lf($text));
    }
}
