<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Support\Str;

/**
 * The states a factory gets from the draft: one per enum value, per boolean that reads as a state, per nullable
 * "happened at" timestamp, one to fill a foreign key the definition leaves null, and whatever `factories:` lists.
 *
 * A name that Laravel's factory already uses (`count`, `make`, ...) or that two columns would share is written with its
 * column in front (`statusOpen`) instead of being refused, and a state the draft names itself wins over a generated one.
 * Laravel's own `trashed()` is not repeated for soft deletes.
 */
final class FactoryStates
{
    private const RESERVED = [
        'count', 'make', 'create', 'state', 'for', 'has', 'sequence', 'recycle', 'afterMaking', 'afterCreating', 'configure',
        'definition', 'raw', 'times', 'createOne', 'makeOne', 'createMany', 'makeMany', 'createQuietly', 'makeQuietly',
        'newModel', 'modelName', 'trashed', 'connection', 'set', 'with', 'new', 'factory', 'useNamespace', 'guessModelNamesUsing',
    ];

    private const OPPOSITES = [
        'active' => 'inactive', 'enabled' => 'disabled', 'published' => 'unpublished', 'visible' => 'hidden',
        'verified' => 'unverified', 'public' => 'private', 'approved' => 'unapproved', 'confirmed' => 'unconfirmed',
        'featured' => 'unfeatured', 'available' => 'unavailable', 'subscribed' => 'unsubscribed',
    ];

    /**
     * @param  array<string, ForeignTarget>  $foreignKeys
     * @param  callable(string): string  $name  writes a class name (and imports it)
     * @return array<string, State> by method name
     */
    public static function of(string $modelKey, Model $model, array $foreignKeys, DependencyGraph $graph, ModelFactorySpec $spec, callable $name): array
    {
        $states = [];
        $label = Str::headline($model->name());

        foreach ($spec->states as $state => $attributes) {
            $method = Str::camel($state);

            if (preg_match('/^[a-z][A-Za-z0-9]*$/', $method) !== 1 || in_array($method, self::RESERVED, true)) {
                throw DraftException::at("factories.{$modelKey}.states.{$state}", 'is not a name that can be a method of the factory.', 'Use letters and digits, and not a name Laravel\'s factory already uses (count, make, state, ...).');
            }

            $states[$method] = new State($method, array_map(
                fn ($value): string => is_string($value) && str_starts_with($value, '=') ? substr($value, 1) : Exporter::scalar($value),
                $attributes,
            ), "The {$state} state, from the draft.");
        }

        $reserved = array_keys($states);
        $add = function (string $method, array $attributes, string $doc, string $fallback) use (&$states, $reserved): void {
            foreach ([$method, $fallback] as $candidate) {
                if ($candidate !== '' && ! isset($states[$candidate]) && ! in_array($candidate, self::RESERVED, true) && ! in_array($candidate, $reserved, true) && preg_match('/^[a-z][A-Za-z0-9]*$/', $candidate) === 1) {
                    $states[$candidate] = new State($candidate, $attributes, $doc);

                    return;
                }
            }
        };

        // Enum values: a value that appears in two columns is prefixed in both.
        $wanted = [];

        foreach ($model->columns() as $column) {
            $profile = ColumnProfile::from($column);

            if ($profile->base() === 'enum') {
                foreach ($profile->values as $value) {
                    $wanted[self::valueName($value, $profile->name)][] = [$profile->name, $value];
                }
            }
        }

        foreach ($wanted as $method => $uses) {
            foreach ($uses as [$column, $value]) {
                $prefixed = Str::camel($column . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $value));
                $plain = count($uses) > 1 || in_array($method, self::RESERVED, true) ? $prefixed : $method;

                $add($plain, [$column => ValueGuesser::quote($value)], "Set {$column} to {$value}.", $prefixed);
            }
        }

        foreach ($model->columns() as $column) {
            $profile = ColumnProfile::from($column);

            if ($profile->base() === 'boolean' && preg_match('/^(is_|has_|can_)|^(active|enabled|published|visible|verified|public|approved|confirmed|featured|available|subscribed)$/', $profile->name) === 1) {
                $base = preg_replace('/^(is_|has_|can_)/', '', $profile->name) ?? $profile->name;
                $opposite = self::OPPOSITES[$base] ?? 'not' . Str::studly($base);

                $add(Str::camel($base), [$profile->name => 'true'], "Mark the {$label} as {$base}.", '');
                $add($opposite, [$profile->name => 'false'], "Mark the {$label} as not {$base}.", '');
            }

            if ($profile->nullable && $profile->isTemporal() && str_ends_with($profile->name, '_at')) {
                $word = Str::afterLast(Str::beforeLast($profile->name, '_at'), '_');

                if (preg_match('/(ed|sent|paid|read|seen)$/', $word) === 1) {
                    $negative = str_ends_with($word, 'ed') ? 'un' . $word : 'not' . Str::studly($word);

                    $add(Str::camel($word), [$profile->name => 'now()'], "Mark the {$label} as {$word}.", '');
                    $add($negative, [$profile->name => 'null'], "Mark the {$label} as not {$word}.", '');
                }
            }
        }

        foreach ($foreignKeys as $column => $target) {
            if ($target->nullable && ($target->self || $graph->isBroken($modelKey, $column))) {
                $add(
                    'with' . Str::studly($target->relation),
                    [$column => ForeignExpression::make($target, $name)],
                    'Give the ' . $label . ' its ' . Str::headline($target->relation) . '.',
                    '',
                );
            }
        }

        return $states;
    }

    private static function valueName(string $value, string $column): string
    {
        $name = Str::camel((string) preg_replace('/[^A-Za-z0-9]+/', '_', $value));

        return preg_match('/^[a-z][A-Za-z0-9]*$/', $name) === 1 ? $name : Str::camel($column . '_' . $value);
    }
}
