<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Generators;

use Blueprint\Generators\SeederGenerator as BlueprintSeederGenerator;
use Blueprint\Tree;
use Dakotajune\Seeders\Support\DependencyGraph;
use Dakotajune\Seeders\Support\DraftPlan;
use Dakotajune\Seeders\Support\Options;
use Dakotajune\Seeders\Support\SeederRenderer;

/**
 * Takes the place of Blueprint's seeder generator: reference and demo seeders, the order the foreign keys need, and a
 * `DatabaseSeeder` that runs them. It writes nothing for a draft that plans no seeding, as Blueprint's does not.
 */
final class SeederGenerator extends BlueprintSeederGenerator
{
    use GeneratesFiles;

    protected function render(Tree $tree, DraftPlan $plan, Options $options): array
    {
        return (new SeederRenderer($options->settings($plan), $plan, $options->seed, $options->anchor, $options->environments, $options->docblocks))
            ->render($tree, DependencyGraph::of($tree));
    }
}
