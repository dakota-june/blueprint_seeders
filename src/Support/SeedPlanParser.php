<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * Reads `seeders:`, `factories:` and the `fake:` tokens on column lines out of the parsed draft. Every mistake is a
 * DraftException that starts with the path to it (`seeders.demo.Contact.count`) and lists what would have been allowed.
 */
final class SeedPlanParser
{
    private const SEEDERS = ['seed', 'reference', 'demo'];

    private const DEMO = ['count', 'for', 'has', 'state'];

    private const FACTORY_MODEL = ['fake', 'states', 'null_chance'];

    /**
     * @param  array<string, mixed>  $tokens  what Blueprint parsed from the draft
     */
    public static function parse(array $tokens): DraftPlan
    {
        $legacy = [];
        $seed = null;
        $reference = [];
        $demo = [];
        $seeders = $tokens['seeders'] ?? null;

        if (is_string($seeders)) {
            $legacy = array_values(array_filter(array_map('trim', preg_split('/,/', $seeders) ?: [])));
        } elseif (is_array($seeders)) {
            self::allow('seeders', $seeders, self::SEEDERS);

            if (array_key_exists('seed', $seeders)) {
                $seed = self::integer($seeders['seed'], 'seeders.seed');
            }

            foreach (self::map($seeders['reference'] ?? [], 'seeders.reference') as $model => $spec) {
                $reference[(string) $model] = self::reference((string) $model, $spec);
            }

            foreach (self::map($seeders['demo'] ?? [], 'seeders.demo') as $model => $spec) {
                $demo[(string) $model] = self::demo((string) $model, $spec);
            }
        } elseif ($seeders !== null) {
            throw DraftException::at('seeders', 'expected a list of model names (Post, Comment) or a map with ' . implode(', ', self::SEEDERS) . '.');
        }

        return new DraftPlan(
            seed: $seed,
            legacy: $legacy,
            reference: $reference,
            demo: $demo,
            factories: self::factories($tokens['factories'] ?? null),
            hints: self::hints($tokens['models'] ?? []),
        );
    }

    // -- seeders ------------------------------------------------------------------------------------------------------

    private static function reference(string $model, mixed $spec): ReferenceSpec
    {
        $where = "seeders.reference.{$model}";
        $spec = self::map($spec, $where);
        self::allow($where, $spec, ['key', 'rows']);

        $key = null;

        if (isset($spec['key'])) {
            if (! is_string($spec['key']) || $spec['key'] === '') {
                throw DraftException::at("{$where}.key", 'expected the name of a column.');
            }

            $key = $spec['key'];
        }

        $rows = [];
        $given = $spec['rows'] ?? null;

        if (! is_array($given) || $given === []) {
            throw DraftException::at("{$where}.rows", 'expected a list of rows, like [Customer, Supplier], or a map of key => columns.', 'Block lists with dashes cannot be used in a Blueprint draft.');
        }

        foreach ($given as $at => $row) {
            $rowWhere = "{$where}.rows[{$at}]";

            if (is_array($row)) {
                $rows[] = new ReferenceRow(array_is_list($given) ? null : $at, self::attributes($row, $rowWhere));
            } elseif (is_scalar($row) && array_is_list($given)) {
                $rows[] = new ReferenceRow($row);
            } else {
                throw DraftException::at($rowWhere, 'expected a value, or a map of columns like { name: Customer }.');
            }
        }

        return new ReferenceSpec($model, $key, $rows);
    }

    private static function demo(string $model, mixed $spec): DemoSpec
    {
        $where = "seeders.demo.{$model}";

        if (! is_array($spec)) {
            return new DemoSpec($model, CountSpec::parse($spec, $where));
        }

        self::allow($where, $spec, self::DEMO);

        $for = [];

        foreach (self::map($spec['for'] ?? [], "{$where}.for") as $relation => $how) {
            if (! in_array($how, ['random', 'new'], true)) {
                throw DraftException::at("{$where}.for.{$relation}", 'expected random (an existing row) or new (made by its factory).');
            }

            $for[(string) $relation] = $how;
        }

        $has = [];

        foreach (self::map($spec['has'] ?? [], "{$where}.has") as $relation => $count) {
            $has[(string) $relation] = CountSpec::parse($count, "{$where}.has.{$relation}");
        }

        $states = $spec['state'] ?? [];
        $states = is_string($states) ? array_map('trim', explode(',', $states)) : $states;

        if (! is_array($states) || array_filter($states, fn ($s): bool => ! is_string($s) || $s === '') !== []) {
            throw DraftException::at("{$where}.state", 'expected the name of a factory state, or a list of them like [vip].');
        }

        return new DemoSpec($model, CountSpec::parse($spec['count'] ?? 5, "{$where}.count"), $for, $has, array_values($states));
    }

    // -- factories ----------------------------------------------------------------------------------------------------

    private static function factories(mixed $section): FactoryDraft
    {
        if ($section === null) {
            return new FactoryDraft();
        }

        $guesses = [];
        $models = [];

        foreach (self::map($section, 'factories') as $name => $value) {
            if ($name === 'guesses') {
                foreach (self::map($value, 'factories.guesses') as $pattern => $expression) {
                    $guesses[(string) $pattern] = self::text($expression, "factories.guesses.{$pattern}");
                }

                continue;
            }

            $where = "factories.{$name}";
            $spec = self::map($value, $where);
            self::allow($where, $spec, self::FACTORY_MODEL);

            $fake = [];

            foreach (self::map($spec['fake'] ?? [], "{$where}.fake") as $column => $expression) {
                $fake[(string) $column] = self::text($expression, "{$where}.fake.{$column}");
            }

            $states = [];

            foreach (self::map($spec['states'] ?? [], "{$where}.states") as $state => $attributes) {
                if (! is_array($attributes)) {
                    throw DraftException::at("{$where}.states.{$state}", 'expected a map of columns, like { is_vip: true }.');
                }

                $states[(string) $state] = self::attributes($attributes, "{$where}.states.{$state}");
            }

            $chance = null;

            if (array_key_exists('null_chance', $spec)) {
                if (! is_int($spec['null_chance']) && ! is_float($spec['null_chance']) || $spec['null_chance'] < 0 || $spec['null_chance'] > 1) {
                    throw DraftException::at("{$where}.null_chance", 'expected a number from 0 (never null) to 1 (always null).');
                }

                $chance = (float) $spec['null_chance'];
            }

            $models[(string) $name] = new ModelFactorySpec($fake, $states, $chance);
        }

        return new FactoryDraft($guesses, $models);
    }

    /**
     * `fake:` tokens on the column lines of `models:`. Blueprint's lexer drops tokens it does not know, so they are read
     * from the raw draft here. Whitespace inside a token has to be quoted, as in Blueprint's own column syntax.
     *
     * @return array<string, array<string, string>>
     */
    private static function hints(mixed $models): array
    {
        $hints = [];

        foreach (is_array($models) ? $models : [] as $model => $columns) {
            foreach (is_array($columns) ? $columns : [] as $column => $definition) {
                if (! is_string($definition)) {
                    continue;
                }

                foreach (preg_split('#("|\').*?\1(*SKIP)(*FAIL)|\s+#', $definition) ?: [] as $token) {
                    if (str_starts_with($token, 'fake:') && strlen($token) > 5) {
                        $hints[(string) $model][(string) $column] = preg_replace('/^(["\'])(.*)\1$/s', '$2', substr($token, 5));
                    }
                }
            }
        }

        return $hints;
    }

    // -- helpers ------------------------------------------------------------------------------------------------------

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value, string $where): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (! is_array($value)) {
            throw DraftException::at($where, 'expected a map.');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $given
     * @param  list<string>  $allowed
     */
    private static function allow(string $where, array $given, array $allowed): void
    {
        foreach (array_keys($given) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw DraftException::at($where, 'unknown option "' . $key . '".', 'Allowed: ' . implode(', ', $allowed) . '.');
            }
        }
    }

    private static function integer(mixed $value, string $where): int
    {
        if (! is_int($value)) {
            throw DraftException::at($where, 'expected a whole number.');
        }

        return $value;
    }

    private static function text(mixed $value, string $where): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw DraftException::at($where, 'expected a faker method, like safeEmail or numberBetween(1,5), or a PHP expression.');
        }

        return trim($value);
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return array<string, scalar|null>
     */
    private static function attributes(array $row, string $where): array
    {
        $attributes = [];

        foreach ($row as $column => $value) {
            if (! is_scalar($value) && $value !== null) {
                throw DraftException::at("{$where}.{$column}", 'expected a single value.');
            }

            $attributes[(string) $column] = $value;
        }

        return $attributes;
    }
}
