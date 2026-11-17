<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * Is this the DatabaseSeeder Laravel ships, with nothing of the project's own in it? Then replacing it loses nothing.
 * Comments, `use` lines and whitespace are ignored, so the Laravel 10, 11 and 12 versions all match; a call to another
 * seeder, or any other statement, does not.
 */
final class StockDatabaseSeeder
{
    public static function matches(string $source): bool
    {
        $text = Eol::lf($source);
        $text = (string) preg_replace('~/\*.*?\*/~s', '', $text);
        $text = (string) preg_replace('~//[^\n]*~', '', $text);
        $text = (string) preg_replace('/^\s*use\s+[^;]+;/m', '', $text);
        $text = (string) preg_replace('/\s+/', '', $text);

        $user = '(\\\\?App\\\\Models\\\\)?User::factory\(\)->create\(\[\'name\'=>\'TestUser\',\'email\'=>\'test@example\.com\',?\]\);';

        return preg_match('/^<\?phpnamespaceDatabase\\\\Seeders;classDatabaseSeederextendsSeeder\{publicfunctionrun\(\):void\{(' . $user . ')?\}\}$/', $text) === 1;
    }
}
