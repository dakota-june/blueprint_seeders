<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Console;

use Blueprint\Blueprint;
use Dakotajune\Seeders\Generators\FactoryGenerator;
use Dakotajune\Seeders\Generators\SeederGenerator;
use Dakotajune\Seeders\Lexers\SeedPlanLexer;
use Dakotajune\Seeders\Support\Decision;
use Dakotajune\Seeders\Support\DependencyGraph;
use Dakotajune\Seeders\Support\DraftException;
use Dakotajune\Seeders\Support\DraftPlan;
use Dakotajune\Seeders\Support\FileGuard;
use Dakotajune\Seeders\Support\Options;
use Dakotajune\Seeders\Support\Report;
use Dakotajune\Seeders\Support\UnifiedDiff;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Runs the factory and seeder generators the way `blueprint:build` would and writes nothing: which files would be
 * created, rewritten, left as they are or skipped (and why), in what order the models are seeded, and what the author of
 * the draft should know. `--diff` adds what would change in each file.
 */
final class PreviewCommand extends Command
{
    protected $signature = 'seeders:preview
                            {draft? : The path to the draft file, default: draft.yaml or draft.yml }
                            {--diff : Show what would change in each file }
                            {--only= : factories or seeders, to look at one of them }';

    protected $description = 'Show what blueprint:build would do to the factories and seeders, without writing anything';

    public function handle(Filesystem $files): int
    {
        $file = $this->argument('draft') ?? (file_exists('draft.yml') ? 'draft.yml' : 'draft.yaml');

        if (! $files->exists($file)) {
            $this->error('Draft file could not be found: ' . $file);

            return 1;
        }

        $only = (string) $this->option('only');

        if (! in_array($only, ['', 'factories', 'seeders'], true)) {
            $this->error('--only must be factories or seeders.');

            return 1;
        }

        $blueprint = resolve(Blueprint::class);
        $cache = $files->exists('.blueprint') ? $blueprint->parse($files->get('.blueprint')) : [];
        $contents = $files->get($file);
        $tokens = $blueprint->parse($contents, preg_match('/^\s+indexes:\R/m', $contents) !== 1);
        $tokens['cache'] = $cache['models'] ?? [];

        try {
            $tree = $blueprint->analyze($tokens);
            $options = Options::fromConfig();
            $guard = new FileGuard($files, $options->overwrite, true);
            $results = [];

            foreach ([
                'factories' => new FactoryGenerator($files),
                'seeders' => new SeederGenerator($files),
            ] as $name => $generator) {
                if ($only === '' || $only === $name) {
                    array_push($results, ...$generator->generate($tree, $guard, $options));
                }
            }

            $graph = DependencyGraph::of($tree);
        } catch (DraftException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $counts = [];

        foreach ($results as [$rendered, $decision]) {
            $counts[$decision->action] = ($counts[$decision->action] ?? 0) + 1;
            $this->line($this->line_for($decision));

            if ($decision->reason !== null) {
                $this->line('    ' . $decision->reason);
            }

            if ($this->option('diff') && in_array($decision->action, ['created', 'updated'], true)) {
                $this->line(rtrim(UnifiedDiff::make((string) $decision->old, (string) $decision->new, $decision->path)));
            }
        }

        $plan = $tree->toArray()[SeedPlanLexer::KEY] ?? new DraftPlan();
        $this->summary($plan, $graph);

        $this->newLine();
        $this->line(implode(', ', array_map(fn (string $action, int $n): string => "{$n} {$action}", array_keys($counts), $counts)) ?: 'Nothing to generate.');

        foreach (Report::drain() as $note) {
            $this->warn($note);
        }

        return 0;
    }

    private function line_for(Decision $decision): string
    {
        $label = match ($decision->action) {
            'created' => '<fg=green>create   </>',
            'updated' => '<fg=yellow>update   </>',
            'unchanged' => 'unchanged',
            default => '<fg=red>skip     </>',
        };

        return "{$label} {$decision->path}";
    }

    private function summary(DraftPlan $plan, DependencyGraph $graph): void
    {
        $seeded = [...array_keys($plan->reference), ...array_keys($plan->demo), ...$plan->legacy];

        if ($seeded === []) {
            return;
        }

        $order = array_values(array_filter($graph->order(), fn (string $model): bool => in_array($model, $seeded, true)));

        $this->newLine();
        $this->line('Seeded in this order: ' . implode(' > ', $order));

        foreach ($plan->demo as $name => $spec) {
            $count = $spec->count->isFixed() ? (string) $spec->count->min : "{$spec->count->min} to {$spec->count->max}";
            $this->line("    {$name}: {$count} rows");
        }
    }
}
