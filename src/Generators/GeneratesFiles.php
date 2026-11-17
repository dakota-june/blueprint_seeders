<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Generators;

use Blueprint\Tree;
use Dakotajune\Seeders\Lexers\SeedPlanLexer;
use Dakotajune\Seeders\Support\Decision;
use Dakotajune\Seeders\Support\DraftPlan;
use Dakotajune\Seeders\Support\FileGuard;
use Dakotajune\Seeders\Support\Options;
use Dakotajune\Seeders\Support\OutputShape;
use Dakotajune\Seeders\Support\Rendered;
use Dakotajune\Seeders\Support\Report;

/**
 * What the two generators share: render, write through the guard, tell Blueprint, and keep what the author should hear.
 * Preview runs the same code with a guard that writes nothing.
 */
trait GeneratesFiles
{
    /**
     * @return list<Rendered>
     */
    abstract protected function render(Tree $tree, DraftPlan $plan, Options $options): array;

    public function output(Tree $tree): array
    {
        $this->output = [];
        $options = Options::fromConfig();
        $guard = new FileGuard($this->filesystem, $options->overwrite, $options->dryRun);

        foreach ($this->generate($tree, $guard, $options) as [$file, $decision]) {
            OutputShape::record($this->output, $decision, $file->type);
        }

        if ($options->dryRun && $this->output !== []) {
            Report::add('This was a dry run (seeders.dry_run is on): the files listed were not written.');
        }

        return $this->output;
    }

    /**
     * @return list<array{0: Rendered, 1: Decision}>
     */
    public function generate(Tree $tree, FileGuard $guard, ?Options $options = null): array
    {
        $this->tree = $tree;
        $options ??= Options::fromConfig();
        $plan = $tree->toArray()[SeedPlanLexer::KEY] ?? new DraftPlan();
        $results = [];
        $files = $this->render($tree, $plan, $options);
        $calls = array_values(array_filter(array_map(
            fn (Rendered $file): ?string => match (basename($file->path)) {
                'ReferenceSeeder.php' => '$this->call(ReferenceSeeder::class);',
                'DemoSeeder.php' => 'DemoSeeder::class, inside the environment check you want',
                default => null,
            },
            $files,
        )));

        foreach ($files as $file) {
            foreach ($file->notes as $note) {
                Report::add($note);
            }

            $decision = $guard->write($file->path, $file->source, $file->guardHandWritten);
            $results[] = [$file, $decision];

            if ($decision->action === 'skipped') {
                Report::add("{$file->path}: not written: {$decision->reason}.");

                if ($file->guardHandWritten && $calls !== []) {
                    Report::add("{$file->path}: to seed what the draft describes, add to its run(): " . implode('  ', $calls));
                }
            }
        }

        return $results;
    }
}
