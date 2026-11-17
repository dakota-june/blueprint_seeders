<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * What the author of the draft should hear once the build is done. Blueprint's own output has no room for it: its build
 * command matches on the action name of each result and stops on one it does not know, so notes cannot travel in it.
 */
final class Report
{
    /** @var list<string> */
    private static array $notes = [];

    public static function add(string $note): void
    {
        if (! in_array($note, self::$notes, true)) {
            self::$notes[] = $note;
        }
    }

    /** @return list<string> */
    public static function drain(): array
    {
        $notes = self::$notes;
        self::$notes = [];

        return $notes;
    }
}
