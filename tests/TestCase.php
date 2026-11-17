<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Tests;

use Blueprint\BlueprintServiceProvider;
use Dakotajune\BlueprintModels\BlueprintModelsServiceProvider;
use Dakotajune\Seeders\SeedersServiceProvider;
use Dakotajune\Seeders\Support\Report;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** Folders the generators write into, relative to the app. */
    private const WATCHED = ['app/Models', 'database/factories', 'database/seeders', 'database/migrations'];

    private string $originalDirectory = '';

    /** @var array<string, string> path => contents, as the app skeleton shipped it */
    private array $files = [];

    /** @var list<string> */
    private array $directories = [];

    protected function getPackageProviders($app): array
    {
        $providers = [BlueprintServiceProvider::class, SeedersServiceProvider::class];

        // When it is installed it is used, so every test also runs next to it.
        if (class_exists(BlueprintModelsServiceProvider::class)) {
            $providers[] = BlueprintModelsServiceProvider::class;
        }

        return $providers;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Blueprint's generators write to paths relative to the working directory; make that the app, not the package.
        $this->originalDirectory = (string) getcwd();
        chdir(base_path());

        $this->snapshot();
        Report::drain();
    }

    protected function tearDown(): void
    {
        $this->restore();
        Report::drain();
        chdir($this->originalDirectory);

        parent::tearDown();
    }

    /**
     * What the skeleton has now. The generators overwrite files that are part of it (DatabaseSeeder, for one), so tests
     * put back what was there rather than deleting by name.
     */
    private function snapshot(): void
    {
        $this->files = [];
        $this->directories = [];

        foreach (self::WATCHED as $folder) {
            foreach ($this->walk(base_path($folder)) as $path => $isFile) {
                $isFile ? $this->files[$path] = (string) file_get_contents($path) : $this->directories[] = $path;
            }
        }
    }

    private function restore(): void
    {
        foreach (self::WATCHED as $folder) {
            foreach (array_reverse($this->walk(base_path($folder)), true) as $path => $isFile) {
                if ($isFile && ! isset($this->files[$path])) {
                    @unlink($path);
                } elseif (! $isFile && ! in_array($path, $this->directories, true)) {
                    @rmdir($path);
                }
            }
        }

        foreach ($this->files as $path => $contents) {
            if (! is_file($path) || file_get_contents($path) !== $contents) {
                @mkdir(dirname($path), 0777, true);
                file_put_contents($path, $contents);
            }
        }

        foreach (['.blueprint', 'draft.yaml', 'draft.yml'] as $file) {
            @unlink(base_path($file));
        }
    }

    /**
     * @return array<string, bool> path => is a file, parents before children
     */
    private function walk(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $found = [];

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $name;

            if (is_dir($path)) {
                $found[$path] = false;
                $found += $this->walk($path);
            } else {
                $found[$path] = true;
            }
        }

        return $found;
    }

    /** Writes the draft where Blueprint's commands look for it. */
    protected function draft(string $yaml, string $name = 'draft.yaml'): string
    {
        file_put_contents(base_path($name), $yaml);

        return $name;
    }
}
