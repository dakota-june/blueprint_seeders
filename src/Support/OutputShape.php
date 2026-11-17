<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Generators\AbstractClassGenerator;
use Illuminate\Filesystem\Filesystem;

/**
 * How a Blueprint release wants the files a generator made listed in its result.
 *
 * 2.10 has `create()` note each path as a plain string, and its build command prints them as they are. Later releases
 * leave it to the generator and want `[type, path]`, which the build command and `Builder` read by position. The result is
 * what Blueprint itself does, found by asking the installed class rather than by looking at a version number.
 *
 * Only `created`, `updated`, `skipped` and `deleted` are actions the build command knows. A file this package rewrites
 * is listed as `created`, as Blueprint lists one it overwrites, so `blueprint:erase` (which removes only `created`) keeps
 * working. A file it leaves alone is `skipped`, which erase never removes.
 */
final class OutputShape
{
    private static ?bool $typed = null;

    /**
     * @param  array<string, list<mixed>>  $output
     */
    public static function record(array &$output, Decision $decision, string $type, ?bool $typed = null): void
    {
        $action = $decision->action === 'skipped' ? 'skipped' : 'created';
        $output[$action][] = ($typed ?? self::typed()) ? [$type, $decision->path] : $decision->path;
    }

    /** Does this Blueprint want `[type, path]`? */
    public static function typed(): bool
    {
        return self::$typed ??= self::probe();
    }

    private static function probe(): bool
    {
        $files = new class extends Filesystem
        {
            public function exists($path)
            {
                return true;
            }

            public function put($path, $contents, $lock = false)
            {
                return 0;
            }
        };

        $probe = new class($files) extends AbstractClassGenerator
        {
            public function notesItsFiles(): bool
            {
                $this->create('probe.php', '');

                return isset($this->output['created']);
            }
        };

        return ! $probe->notesItsFiles();
    }
}
