<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Generators;

use Blueprint\Generators\FactoryGenerator as BlueprintFactoryGenerator;
use Blueprint\Tree;
use Dakotajune\Seeders\Support\DependencyGraph;
use Dakotajune\Seeders\Support\DraftPlan;
use Dakotajune\Seeders\Support\FactoryRenderer;
use Dakotajune\Seeders\Support\Options;

/**
 * Takes the place of Blueprint's factory generator: values from the name and type of each column, states, foreign keys
 * that cannot recurse, and a guard on what it overwrites. Blueprint's class is extended for its type name (`factories`,
 * so `--only` and `--skip` keep working), its constructor and its `output` result, nothing else.
 */
final class FactoryGenerator extends BlueprintFactoryGenerator
{
    use GeneratesFiles;

    protected function render(Tree $tree, DraftPlan $plan, Options $options): array
    {
        $graph = DependencyGraph::of($tree);
        $renderer = new FactoryRenderer($options->settings($plan), $plan, $options->docblocks);
        $files = [];

        foreach ($tree->models() as $key => $model) {
            $files[] = $renderer->render((string) $key, $model, $tree, $graph);
        }

        return $files;
    }
}
