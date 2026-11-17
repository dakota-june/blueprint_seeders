<?php

declare(strict_types=1);

use Blueprint\Blueprint;
use Blueprint\Generators\FactoryGenerator as BlueprintFactoryGenerator;
use Blueprint\Generators\SeederGenerator as BlueprintSeederGenerator;
use Blueprint\Lexers\ModelLexer;
use Blueprint\Lexers\SeederLexer;
use Blueprint\Models\Column;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Dakotajune\Seeders\Lexers\SeedPlanLexer;
use Dakotajune\Seeders\Support\ColumnProfile;
use Dakotajune\Seeders\Support\DependencyGraph;
use Dakotajune\Seeders\Support\DraftPlan;
use Dakotajune\Seeders\Support\FactoryRenderer;
use Dakotajune\Seeders\Support\Rendered;
use Dakotajune\Seeders\Support\SeederRenderer;
use Dakotajune\Seeders\Support\FormatterCatalog;
use Dakotajune\Seeders\Support\Guess;
use Dakotajune\Seeders\Support\Settings;
use Dakotajune\Seeders\Support\ValueGuesser;

/*
 * Helpers for the tests that need no Laravel application: they build Blueprint's own Column, Model and Tree from draft
 * text, with Blueprint's own lexer, so tests see what the generators see. Loaded by Composer (autoload-dev files) and by
 * the offline harness alike.
 */

const FK_DRAFT = <<<'YAML'
models:
  Contact:
    name: string
    contact_type_id: id
    company_id: id nullable
    parent_contact_id: id:contact nullable
  ContactType:
    name: string
  Company:
    name: string
    owner_contact_id: id:contact
  Note:
    body: text
    author_id: id
    owner_id: id foreign:users
    owner_uuid: uuid foreign:users.uuid
  User:
    name: string
YAML;

/** A real Blueprint Column, made by Blueprint's own ModelLexer from a column definition. */
function col(string $name, string $definition): Column
{
    $models = (new ModelLexer())->analyze(['models' => ['Thing' => [$name => $definition]]])['models'];

    return $models['Thing']->column($name);
}

/** The Tree Blueprint would hand to a generator, from draft text (shorthands such as `timestamps` expanded as usual). */
function draft(string $yaml): Tree
{
    $blueprint = new Blueprint();
    $blueprint->registerLexer(new ModelLexer());
    $blueprint->registerLexer(new SeedPlanLexer());

    // As Blueprint's build command does: dashes are only stripped from drafts that do not use `indexes:` lists.
    return $blueprint->analyze($blueprint->parse($yaml, preg_match('/^\s+indexes:\R/m', $yaml) !== 1));
}

function profile(string $definition, string $name = 'x'): ColumnProfile
{
    return ColumnProfile::from(col($name, $definition));
}

/** @param array<string, mixed> $settings Settings constructor arguments */
function guess(string $definition, string $name = 'x', string $model = 'Thing', array $settings = [], ?string $override = null): Guess
{
    $settings += ['catalog' => FormatterCatalog::fromFaker()];

    return (new ValueGuesser(new Settings(...$settings)))->guess(profile($definition, $name), $model, $override);
}

/**
 * Runs a generated expression the way a factory would, against real Faker, and returns what it produced.
 *
 * @return list<mixed>
 */
function sample(Guess $guess, int $times = 200, int $seed = 1): array
{
    $faker = Faker\Factory::create('en_US');
    $faker->seed($seed);

    $context = new class($faker)
    {
        public function __construct(public Faker\Generator $faker) {}

        /** @return list<mixed> */
        public function run(string $expression, int $times): array
        {
            $values = [];

            for ($i = 0; $i < $times; $i++) {
                $values[] = eval("return {$expression};");
            }

            return $values;
        }
    };

    return $context->run($guess->expression, $times);
}


function lints(string $source): bool
{
    try {
        token_get_all($source, TOKEN_PARSE);

        return true;
    } catch (ParseError) {
        return false;
    }
}

function planOf(Tree $tree): DraftPlan
{
    return $tree->toArray()[SeedPlanLexer::KEY];
}

/**
 * The factory the package would write for one model of a draft.
 *
 * @param  array<string, mixed>  $settings  Settings constructor arguments
 */
function factory(string $yaml, string $model, array $settings = [], bool $docblocks = true): Rendered
{
    $tree = draft($yaml);
    $settings += ['catalog' => FormatterCatalog::fromFaker()];

    return (new FactoryRenderer(new Settings(...$settings), planOf($tree), $docblocks))
        ->render((string) $model, $tree->models()[$model], $tree, DependencyGraph::of($tree));
}


/**
 * Every seeder file the package would write for a draft, by path.
 *
 * @param  array<string, mixed>  $options  seed, anchor, environments
 * @return array<string, Rendered>
 */
function seeders(string $yaml, array $options = []): array
{
    $tree = draft($yaml);
    $renderer = new SeederRenderer(
        new Settings(catalog: FormatterCatalog::fromFaker()),
        planOf($tree),
        $options['seed'] ?? null,
        $options['anchor'] ?? null,
        $options['environments'] ?? ['local', 'testing'],
    );

    $files = [];

    foreach ($renderer->render($tree, DependencyGraph::of($tree)) as $file) {
        $files[$file->path] = $file;
    }

    return $files;
}

/**
 * Sets config for the length of a callback and puts back what was there. Works under Laravel and under the offline harness.
 *
 * @template T
 * @param  array<string, mixed>  $values  dotted keys
 * @param  callable(): T  $run
 * @return T
 */
function withConfig(array $values, callable $run): mixed
{
    $before = [];

    foreach (array_keys($values) as $key) {
        $before[$key] = config($key);
    }

    config($values);

    try {
        return $run();
    } finally {
        config($before);
    }
}

/**
 * Runs a callback inside an empty temporary project folder (generators write to relative paths), then puts everything back.
 *
 * @template T
 * @param  callable(string): T  $run
 * @param  array<string, mixed>  $config  dotted keys, for the length of the callback
 * @return T
 */
function inProject(callable $run, array $config = []): mixed
{
    $previous = getcwd();
    $dir = sys_get_temp_dir() . '/seeders-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    chdir($dir);
    Dakotajune\Seeders\Support\Report::drain();

    try {
        return withConfig($config, fn () => $run($dir));
    } finally {
        chdir($previous);
        (new Illuminate\Filesystem\Filesystem())->deleteDirectory($dir);
    }
}

/** A Blueprint as the provider builds it: its own lexers and generators, then ours on top. */
function blueprint(): Blueprint
{
    $blueprint = new Blueprint();
    $blueprint->registerLexer(new ModelLexer());
    $blueprint->registerLexer(new SeederLexer());
    $files = new Illuminate\Filesystem\Filesystem();
    $blueprint->registerGenerator(new BlueprintFactoryGenerator($files));
    $blueprint->registerGenerator(new BlueprintSeederGenerator($files));

    return Dakotajune\Seeders\SeedersServiceProvider::extendBlueprint($blueprint, $files);
}


/** One entry of Blueprint's result as this release spells it: `[type, path]`, or the bare path on 2.10. */
function entry(string $type, string $path): array|string
{
    return Dakotajune\Seeders\Support\OutputShape::typed() ? [$type, $path] : $path;
}

/**
 * @param  list<array|string>  $entries
 * @return list<string>
 */
function paths(array $entries): array
{
    return array_map(fn (array|string $entry): string => is_array($entry) ? $entry[1] : $entry, $entries);
}
