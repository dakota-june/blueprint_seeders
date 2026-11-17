<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Support\Str;

/**
 * Writes the seeders of a draft: one per model that is seeded, in `Reference` (fixed lookup data, safe to run in
 * production, no Faker) or `Demo` (made by the factory, for local work), then `ReferenceSeeder`, `DemoSeeder` and a
 * `DatabaseSeeder` that runs them in an order the foreign keys allow.
 *
 * Nothing it writes needs this package at run time.
 */
final class SeederRenderer
{
    private const SEEDER = 'Illuminate\\Database\\Seeder';

    private const FAKER = 'Faker\\Generator';

    private const DEFAULT_KEYS = ['name', 'slug', 'code', 'key', 'title'];

    /**
     * @param  list<string>  $environments  where `DatabaseSeeder` also runs the demo data
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly DraftPlan $plan,
        private readonly ?int $seed = null,
        private readonly ?string $anchor = null,
        private readonly array $environments = ['local', 'testing'],
        private readonly bool $docblocks = true,
    ) {}

    /**
     * @return list<Rendered>
     */
    public function render(Tree $tree, DependencyGraph $graph): array
    {
        $models = [];

        foreach ($tree->models() as $key => $model) {
            $models[(string) $key] = $model;
        }

        $reference = $this->plan->reference;
        $demo = $this->plan->demo;

        foreach ($this->plan->legacy as $name) {
            $demo[$name] ??= new DemoSpec($name, new CountSpec(5, 5));
        }

        foreach ([...array_keys($reference), ...array_keys($demo)] as $name) {
            if (! isset($models[(string) $name])) {
                $where = isset($reference[$name]) ? 'seeders.reference.' . $name : 'seeders.demo.' . $name;

                throw DraftException::at($where, 'is not a model in this draft.', 'Models: ' . implode(', ', array_keys($models)) . '.');
            }
        }

        if ($reference === [] && $demo === []) {
            return [];
        }

        $extra = [];
        $demoParts = [];

        foreach ($demo as $name => $spec) {
            $demoParts[(string) $name] = $this->demo((string) $name, $spec, $models[(string) $name], $tree, $graph, $models);
            $extra[(string) $name] = $demoParts[(string) $name]['needs'];
        }

        $order = $graph->order($extra);
        $files = [];

        foreach ($order as $name) {
            if (isset($reference[$name])) {
                $files[] = $this->reference($name, $reference[$name], $models[$name]);
            }
        }

        foreach ($order as $name) {
            if (isset($demoParts[$name])) {
                $files[] = $demoParts[$name]['file'];
            }
        }

        $referenceNames = array_values(array_filter($order, fn (string $name): bool => isset($reference[$name])));
        $demoNames = array_values(array_filter($order, fn (string $name): bool => isset($demoParts[$name])));

        if ($referenceNames !== []) {
            $files[] = $this->group('ReferenceSeeder', 'Reference', $referenceNames, 'Reference data: the same rows on every run, so it is safe to seed in production.');
        }

        if ($demoNames !== []) {
            $files[] = $this->group('DemoSeeder', 'Demo', $demoNames, 'Sample data for local work and demos. Do not run it in production.', true);
        }

        $files[] = $this->database($referenceNames !== [], $demoNames !== []);

        return $files;
    }

    // -- reference ----------------------------------------------------------------------------------------------------

    private function reference(string $key, ReferenceSpec $spec, Model $model): Rendered
    {
        $where = 'seeders.reference.' . $key;
        $columns = array_map(fn ($column): string => $column->name(), $model->columns());
        $match = $spec->key ?? $this->keyColumn($model);

        if ($match === null) {
            throw DraftException::at($where, 'has no key column to match its rows on.', 'Add key: <column> under it. Rows are matched on that column, which is what lets the seeder run again without duplicates.');
        }

        if (! in_array($match, $columns, true)) {
            throw DraftException::at($where, "the key column \"{$match}\" is not a column of {$key}.", 'Columns: ' . implode(', ', $columns) . '.');
        }

        $rows = [];

        foreach ($spec->rows as $row) {
            $attributes = $row->attributes;

            if ($row->key !== null) {
                $attributes = [$match => $row->key] + $attributes;
            }

            if (! array_key_exists($match, $attributes)) {
                throw DraftException::at($where, "a row has no value for the key column {$match}.", 'Give each row a value for it, or list the values directly: rows: [a, b].');
            }

            foreach (array_keys($attributes) as $column) {
                if (! in_array($column, $columns, true)) {
                    throw DraftException::at("{$where}.{$column}", "is not a column of {$key}.", 'Columns: ' . implode(', ', $columns) . '.');
                }
            }

            $rows[] = $attributes;
        }

        $imports = new Imports();
        $seeder = $imports->name(self::SEEDER);
        $class = $imports->name(ltrim($model->fullyQualifiedClassName(), '\\'));
        $quoted = ValueGuesser::quote($match);

        $body = ['        $rows = ['];

        foreach ($rows as $attributes) {
            $body[] = '            ' . self::inline($attributes) . ',';
        }

        $body = [
            ...$body,
            '        ];',
            '',
            "        {$class}::unguarded(function () use (\$rows): void {",
            '            foreach ($rows as $row) {',
            "                {$class}::updateOrCreate([{$quoted} => \$row[{$quoted}]], \$row);",
            '            }',
            '        });',
        ];

        return new Rendered(
            path: 'database/seeders/Reference/' . str_replace('\\', '/', $key) . 'Seeder.php',
            source: $this->class(
                'Database\\Seeders\\Reference' . (str_contains($key, '\\') ? '\\' . Str::beforeLast($key, '\\') : ''),
                $imports,
                Str::afterLast($key, '\\') . 'Seeder',
                $seeder,
                "Fixed {$key} rows, matched on {$match}: running it again changes nothing that is already right.",
                $body,
            ),
            type: 'Seeder',
        );
    }

    private function keyColumn(Model $model): ?string
    {
        $profiles = array_map(fn ($column): ColumnProfile => ColumnProfile::from($column), $model->columns());

        foreach ($profiles as $profile) {
            if ($profile->unique && ! $profile->isKey()) {
                return $profile->name;
            }
        }

        foreach (self::DEFAULT_KEYS as $name) {
            foreach ($profiles as $profile) {
                if ($profile->name === $name) {
                    return $name;
                }
            }
        }

        return null;
    }

    // -- demo ---------------------------------------------------------------------------------------------------------

    /**
     * @param  array<string, Model>  $models
     * @return array{file: Rendered, needs: list<string>}
     */
    private function demo(string $key, DemoSpec $spec, Model $model, Tree $tree, DependencyGraph $graph, array $models): array
    {
        $where = 'seeders.demo.' . $key;
        $imports = new Imports();
        $name = fn (string $class): string => $imports->name($class);
        $seeder = $name(self::SEEDER);
        $class = $name(ltrim($model->fullyQualifiedClassName(), '\\'));
        $foreignKeys = ForeignKeys::of($model, $tree);
        $relations = Relations::of($model, $tree);

        $needsFaker = ! $spec->count->isFixed();
        $needs = [];
        $setup = [];
        $chain = [];
        
        // States.
        $factorySpec = $this->plan->factories->models[$key] ?? new ModelFactorySpec();
        $known = array_keys(FactoryStates::of($key, $model, $foreignKeys, $graph, $factorySpec, fn (string $c): string => $c));

        if ($model->usesSoftDeletes()) {
            $known[] = 'trashed';
        }

        foreach ($spec->states as $state) {
            if (! in_array($state, $known, true)) {
                throw DraftException::at("{$where}.state", "\"{$state}\" is not a state of {$key}'s factory.", 'States: ' . implode(', ', $known) . '.');
            }

            $chain[] = "->{$state}()";
        }

        // belongsTo: pick an existing parent.
        foreach ($spec->for as $relation => $mode) {
            $target = $this->foreignTarget($foreignKeys, (string) $relation);

            if ($target === null) {
                throw DraftException::at("{$where}.for.{$relation}", "is not a belongsTo of {$key}.", 'Relationships to pick from: ' . implode(', ', array_map(fn (ForeignTarget $t): string => $t->relation, $foreignKeys)) . '.');
            }

            if ($mode === 'new') {
                continue;
            }

            if ($mode !== 'random') {
                throw DraftException::at("{$where}.for.{$relation}", "expected random or new, got \"{$mode}\".");
            }

            $needsFaker = true;
            $parent = $name($target->class);
            $variable = '$' . Str::camel($target->relation) . 'Keys';
            $column = ValueGuesser::quote($target->key);

            $setup[] = "{$variable} = {$parent}::query()->orderBy({$column})->pluck({$column})->all();";
            $setup[] = "if ({$variable} === []) {";
            $setup[] = "    throw new \\RuntimeException('{$key}: for {$target->relation}: random found no {$parent} rows. Seed {$parent} first.');";
            $setup[] = '}';
            $setup[] = '';
            $chain[] = "->state(fn (): array => [" . ValueGuesser::quote($target->column) . " => \$faker->randomElement({$variable})])";

            if ($target->model !== null) {
                $needs[] = (string) array_search($target->model, $models, true);
            }
        }

        // hasMany, hasOne, morphMany, morphOne, belongsToMany: children for every parent.
        $after = [];
        $captured = [];
        $owner = '$' . Str::camel(Str::afterLast($key, '\\'));

        foreach ($spec->has as $relation => $count) {
            $found = $relations[Str::camel((string) $relation)] ?? null;

            if ($found === null) {
                throw DraftException::at("{$where}.has.{$relation}", "is not something {$key} has.", 'Relationships: ' . ($relations === [] ? 'none declared on the model' : implode(', ', array_keys($relations))) . '.');
            }

            $needsFaker = true;
            $child = $name($found->class);
            $number = $count->isFixed() ? (string) $count->min : "\$faker->numberBetween({$count->min}, {$count->max})";

            if ($found->type === 'belongsToMany') {
                $variable = '$' . Str::camel(Str::singular($found->name)) . 'Ids';
                $setup[] = "{$variable} = {$child}::query()->orderBy('id')->pluck('id')->all();";
                $setup[] = '';
                $captured[] = $variable;
                $after[] = "if ({$variable} !== []) {";
                $after[] = "    {$owner}->{$found->name}()->attach(\$faker->randomElements({$variable}, min({$number}, count({$variable}))));";
                $after[] = '}';

                if ($found->related !== null) {
                    $needs[] = (string) array_search($found->related, $models, true);
                }

                continue;
            }

            $attributes = $this->childAttributes($found, $model, $owner, $tree);
            $many = $found->isMany() ? "->count({$number})" : '';
            $after[] = "{$child}::factory(){$many}->create({$attributes});";
        }

        $needs = array_values(array_unique(array_filter($needs, fn (string $n): bool => $n !== '' && $n !== $key)));

        if ($after !== []) {
            $chain[] = "->afterCreating(function ({$class} {$owner}) use (" . implode(', ', ['$faker', ...array_unique($captured)]) . '): void {';
            $chain = [...$chain, ...array_map(fn (string $line): string => '    ' . $line, $after), '})'];
        }

        $faker = $needsFaker ? $name(self::FAKER) : null;
        $count = $spec->count->isFixed() ? (string) $spec->count->min : "\$faker->numberBetween({$spec->count->min}, {$spec->count->max})";

        $body = [];

        if ($faker !== null) {
            $body[] = "        \$faker = app({$faker}::class);";
            $body[] = '';
        }

        foreach ($setup as $line) {
            $body[] = $line === '' ? '' : '        ' . $line;
        }

        $body[] = "        {$class}::factory()";
        $body[] = "            ->count({$count})";

        foreach ($chain as $line) {
            $body[] = '            ' . $line;
        }

        $body[] = '            ->create();';

        $file = new Rendered(
            path: 'database/seeders/Demo/' . str_replace('\\', '/', $key) . 'Seeder.php',
            source: $this->class(
                'Database\\Seeders\\Demo' . (str_contains($key, '\\') ? '\\' . Str::beforeLast($key, '\\') : ''),
                $imports,
                Str::afterLast($key, '\\') . 'Seeder',
                $seeder,
                "Sample {$class} rows made by the factory. For local work, not production.",
                $body,
            ),
            type: 'Seeder',
        );

        return ['file' => $file, 'needs' => $needs];
    }

    /**
     * @param  array<string, ForeignTarget>  $foreignKeys
     */
    private function foreignTarget(array $foreignKeys, string $relation): ?ForeignTarget
    {
        foreach ($foreignKeys as $target) {
            if ($target->relation === Str::camel($relation)) {
                return $target;
            }
        }

        return null;
    }

    /** The array passed to `create()` so a child points back at its parent. */
    private function childAttributes(Relation $relation, Model $parent, string $owner, Tree $tree): string
    {
        $self = ltrim($parent->fullyQualifiedClassName(), '\\');

        if ($relation->type === 'morphMany' || $relation->type === 'morphOne') {
            $morph = $relation->morph;

            foreach ($relation->related?->columns() ?? [] as $column) {
                if (str_ends_with(strtolower($column->dataType()), 'morphs')) {
                    $morph = $column->name();

                    break;
                }
            }

            return '[' . ValueGuesser::quote($morph . '_id') . " => {$owner}->getKey(), " . ValueGuesser::quote($morph . '_type') . " => {$owner}->getMorphClass()]";
        }

        $column = $relation->foreignKey;
        $value = "{$owner}->getKey()";

        foreach ($relation->related !== null ? ForeignKeys::of($relation->related, $tree) : [] as $target) {
            if ($target->class === $self) {
                $column = $target->column;
                $value = $target->key === 'id' ? $value : "{$owner}->getAttribute(" . ValueGuesser::quote($target->key) . ')';

                break;
            }
        }

        return '[' . ValueGuesser::quote((string) $column) . " => {$value}]";
    }

    // -- the files that run the others --------------------------------------------------------------------------------

    /**
     * @param  list<string>  $names
     */
    private function group(string $class, string $folder, array $names, string $doc, bool $demo = false): Rendered
    {
        $imports = new Imports();
        $seeder = $imports->name(self::SEEDER);
        $seed = $demo ? ($this->plan->seed ?? $this->seed) : null;
        $anchored = $demo && $this->anchor !== null;
        $items = array_map(fn (string $name): string => $imports->name('Database\\Seeders\\' . $folder . '\\' . $name . 'Seeder') . '::class,', $names);
        $body = [];

        if ($seed !== null) {
            // seed() fixes the numbers; unique(true) forgets the values Faker has already handed out, or a second run in the
            // same process (a test suite) would skip them and make different rows.
            $body[] = '        $faker = app(' . $imports->name(self::FAKER) . '::class);';
            $body[] = "        \$faker->seed({$seed});";
            $body[] = '        $faker->unique(true);';
        }

        if ($anchored) {
            $carbon = $imports->name('Illuminate\\Support\\Carbon');
            $body[] = "        {$carbon}::setTestNow(" . ValueGuesser::quote((string) $this->anchor) . ');';
        }

        if ($body !== []) {
            $body[] = '';
        }

        $indent = $anchored ? '    ' : '';
        $call = [$indent . '        $this->call([', ...array_map(fn (string $item): string => $indent . '            ' . $item, $items), $indent . '        ]);'];

        if ($anchored) {
            $body = [...$body, '        try {', ...$call, '        } finally {', "            {$carbon}::setTestNow();", '        }'];
        } else {
            $body = [...$body, ...$call];
        }

        return new Rendered('database/seeders/' . $class . '.php', $this->class('Database\\Seeders', $imports, $class, $seeder, $doc, $body), type: 'Seeder');
    }

    private function database(bool $reference, bool $demo): Rendered
    {
        $imports = new Imports();
        $seeder = $imports->name(self::SEEDER);
        $body = [];

        if ($reference) {
            $body[] = '        $this->call(ReferenceSeeder::class);';
        }

        if ($demo) {
            $environments = '[' . implode(', ', array_map(fn (string $e): string => ValueGuesser::quote($e), $this->environments)) . ']';

            if ($reference) {
                $body[] = '';
            }

            $body[] = "        if (app()->environment({$environments})) {";
            $body[] = '            $this->call(DemoSeeder::class);';
            $body[] = '        }';
        }

        return new Rendered(
            'database/seeders/DatabaseSeeder.php',
            $this->class('Database\\Seeders', $imports, 'DatabaseSeeder', $seeder, 'Seed the application: reference data everywhere, demo data only in ' . implode(' and ', $this->environments) . '.', $body),
            type: 'Seeder',
            guardHandWritten: true,
        );
    }

    /**
     * @param  list<string>  $body  the lines of run(), already indented
     */
    private function class(string $namespace, Imports $imports, string $class, string $parent, string $doc, array $body): string
    {
        $out = ['<?php', '', 'namespace ' . $namespace . ';', ''];

        foreach ($imports->lines() as $line) {
            $out[] = $line;
        }

        $out = [...$out, '', "class {$class} extends {$parent}", '{'];

        if ($this->docblocks) {
            $out = [...$out, '    /**', '     * ' . $doc, '     */'];
        }

        $out = [...$out, '    public function run(): void', '    {', ...$body, '    }', '}'];

        return implode("\n", $out) . "\n";
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function inline(array $values): string
    {
        $parts = [];

        foreach ($values as $key => $value) {
            $parts[] = (array_is_list($values) ? '' : Exporter::scalar($key) . ' => ') . (is_array($value) ? self::inline($value) : Exporter::scalar($value));
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
