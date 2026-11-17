<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Support\Str;

/**
 * Writes the factory of one model.
 *
 * Every column gets a value from {@see ValueGuesser}, unless it is a foreign key or a morph, which need the other models
 * and are decided here. The order of precedence for a column is: `fake:` on its line in the draft, `fake:` under
 * `factories:`, the project's `guesses`, then the built-in rules.
 */
final class FactoryRenderer
{
    private const FACTORY = 'Illuminate\\Database\\Eloquent\\Factories\\Factory';

    public function __construct(
        private readonly Settings $settings,
        private readonly DraftPlan $plan,
        private readonly bool $docblocks = true,
    ) {}

    public function render(string $key, Model $model, Tree $tree, DependencyGraph $graph): Rendered
    {
        $spec = $this->plan->factories->models[$key] ?? new ModelFactorySpec();
        $hints = $this->plan->hints[$key] ?? [];
        $settings = new Settings($spec->nullChance ?? $this->settings->nullChance, $this->settings->guesses, $this->settings->catalog);
        $guesser = new ValueGuesser($settings);

        $imports = new Imports();
        $name = fn (string $class): string => $imports->name($class);
        $factory = $name(self::FACTORY);
        $modelClass = ltrim($model->fullyQualifiedClassName(), '\\');
        $short = $name($modelClass);

        $foreignKeys = ForeignKeys::of($model, $tree);
        $before = [];
        $attributes = [];
        $members = [];
        $notes = [];

        foreach ($model->columns() as $column) {
            $profile = ColumnProfile::from($column);
            $override = $hints[$profile->name] ?? $spec->fake[$profile->name] ?? null;

            if ($override === null && str_ends_with($profile->type, 'morphs')) {
                $this->morph($key, $profile, $model, $tree, $name, $before, $attributes, $notes);

                continue;
            }

            $target = $foreignKeys[$profile->name] ?? null;

            if ($target !== null && $override === null) {
                $attributes[$profile->name] = $this->foreign($key, $target, $graph, $settings, $name);

                continue;
            }

            $guess = $guesser->guess($profile, $key, $override);
            $notes = [...$notes, ...$guess->notes];

            foreach ($guess->imports as $class) {
                $name($class);
            }

            $members = [...$members, ...$guess->members];

            if (! $guess->skip) {
                $attributes[$profile->name] = $guess->expression;
            }
        }

        $states = FactoryStates::of($key, $model, $foreignKeys, $graph, $spec, $name);
        $namespace = 'Database\\Factories' . (str_contains($key, '\\') ? '\\' . Str::beforeLast($key, '\\') : '');
        $class = Str::afterLast($key, '\\') . 'Factory';

        $out = ['<?php', '', 'namespace ' . $namespace . ';', ''];

        foreach ($imports->lines() as $line) {
            $out[] = $line;
        }

        $out[] = '';

        if ($this->docblocks) {
            $out = [...$out, '/**', " * @extends {$factory}<{$short}>", ' */'];
        }

        $out[] = "class {$class} extends {$factory}";
        $out[] = '{';

        if ($this->docblocks) {
            $out = [...$out, '    /**', "     * The model this factory makes.", '     *', "     * @var class-string<{$short}>", '     */'];
        }

        $out[] = "    protected \$model = {$short}::class;";

        foreach ($members as $declaration) {
            $out = [...$out, '', '    ' . $declaration];
        }

        $out[] = '';

        if ($this->docblocks) {
            $out = [...$out, '    /**', '     * The default state of a ' . Str::headline($model->name()) . '.', '     *', '     * @return array<string, mixed>', '     */'];
        }

        $out[] = '    public function definition(): array';
        $out[] = '    {';

        foreach ($before as $statement) {
            $out[] = '        ' . $statement;
        }

        if ($before !== []) {
            $out[] = '';
        }

        $out[] = '        return [';

        foreach ($attributes as $column => $expression) {
            $out[] = '            ' . ValueGuesser::quote($column) . ' => ' . $expression . ',';
        }

        $out[] = '        ];';
        $out[] = '    }';

        foreach ($states as $state) {
            $out[] = '';

            if ($this->docblocks) {
                $out = [...$out, '    /**', '     * ' . $state->doc, '     */'];
            }

            $out[] = "    public function {$state->name}(): static";
            $out[] = '    {';
            $out[] = '        return $this->state(fn (array $attributes): array => [';

            foreach ($state->attributes as $column => $expression) {
                $out[] = '            ' . ValueGuesser::quote($column) . ' => ' . $expression . ',';
            }

            $out[] = '        ]);';
            $out[] = '    }';
        }

        $out[] = '}';

        return new Rendered(
            path: 'database/factories/' . str_replace('\\', '/', $key) . 'Factory.php',
            source: implode("\n", $out) . "\n",
            notes: $notes,
        );
    }

    /**
     * @param  callable(string): string  $name
     */
    private function foreign(string $key, ForeignTarget $target, DependencyGraph $graph, Settings $settings, callable $name): string
    {
        if ($target->self) {
            if (! $target->nullable) {
                throw DraftException::column(
                    $key,
                    $target->column,
                    'points at its own table and cannot be null, so no row can be made first',
                    'Make it nullable, or say how to fill it with fake: on the column.',
                );
            }

            return 'null';
        }

        if ($graph->isBroken($key, $target->column)) {
            return 'null';
        }

        $expression = ForeignExpression::make($target, $name);

        if (! $target->nullable || $settings->nullChance <= 0.0) {
            return $expression;
        }

        return '$this->faker->boolean(' . (int) round((1 - $settings->nullChance) * 100) . ') ? ' . $expression . ' : null';
    }

    /**
     * @param  callable(string): string  $name
     * @param  list<string>  $before
     * @param  array<string, string>  $attributes
     * @param  list<string>  $notes
     */
    private function morph(string $key, ColumnProfile $profile, Model $model, Tree $tree, callable $name, array &$before, array &$attributes, array &$notes): void
    {
        $candidates = [];
        $self = ltrim($model->fullyQualifiedClassName(), '\\');

        foreach ($tree->models() as $other) {
            foreach (['morphMany', 'morphOne'] as $type) {
                foreach ($other->relationships()[$type] ?? [] as $reference) {
                    $related = $tree->modelForContext(Str::studly(Str::afterLast(Str::before((string) $reference, ':'), '\\')));

                    if ($related !== null && ltrim($related->fullyQualifiedClassName(), '\\') === $self) {
                        $candidates[ltrim($other->fullyQualifiedClassName(), '\\')] = true;
                    }
                }
            }
        }

        if ($candidates === []) {
            $notes[] = "{$key}.{$profile->name}: nothing says which models it may belong to, so its {$profile->name}_id and {$profile->name}_type are left out. "
                . 'Declare morphMany or morphOne on those models, or give both columns a value in a state.';

            return;
        }

        $variable = '$' . Str::camel($profile->name);
        $classes = array_map(fn (string $class): string => $name($class) . '::class', array_keys($candidates));

        $before[] = $variable . ' = ' . (count($classes) === 1 ? $classes[0] : '$this->faker->randomElement([' . implode(', ', $classes) . '])') . ';';
        $attributes[$profile->name . '_id'] = $variable . '::factory()';
        $attributes[$profile->name . '_type'] = '(new ' . $variable . ')->getMorphClass()';
    }
}
