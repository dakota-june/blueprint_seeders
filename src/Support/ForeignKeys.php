<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Column;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Support\Str;

/**
 * The foreign keys of a model, found the way Blueprint itself finds them (its factory generator and its lexer), so a
 * column that gets a constraint in the migration gets a related factory here, and nothing else does.
 *
 * A column is a foreign key when it is marked `foreign` (`foreign`, `foreign:users`, `foreign:users.uuid`), or is of
 * type `id` (`user_id: id`, `owner_id: id:user`), or is a uuid/ulid whose name ends in `_id`.
 */
final class ForeignKeys
{
    /**
     * @return array<string, ForeignTarget> by column name, in the order of the columns
     */
    public static function of(Model $model, Tree $tree): array
    {
        $found = [];

        foreach ($model->columns() as $column) {
            $target = self::target($model, $column, $tree);

            if ($target !== null) {
                $found[$column->name()] = $target;
            }
        }

        return $found;
    }

    private static function target(Model $model, Column $column, Tree $tree): ?ForeignTarget
    {
        $name = $column->name();
        $type = $column->dataType();
        $foreign = $column->isForeignKey();
        $key = 'id';

        if ($foreign) {
            $context = Str::beforeLast($name, '_id');
            $foreign = (string) $foreign;

            if (Str::contains($foreign, '.')) {
                [$context, $key] = explode('.', $foreign, 2);
            } elseif ($foreign !== 'foreign') {
                $context = $foreign;

                if (Str::startsWith($name, $foreign . '_')) {
                    $key = Str::after($name, $foreign . '_');
                } elseif (Str::startsWith($name, Str::snake(Str::singular($foreign)) . '_')) {
                    $key = Str::after($name, Str::snake(Str::singular($foreign)) . '_');
                } elseif (! Str::endsWith($name, '_id')) {
                    $key = $name;
                }
            }
        } elseif ($type === 'id' && $name !== 'id' || (in_array($type, ['uuid', 'ulid'], true) && Str::endsWith($name, '_id'))) {
            $attributes = array_values($column->attributes());
            $context = (string) ($attributes[0] ?? Str::beforeLast($name, '_id'));
        } else {
            return null;
        }

        $related = $tree->modelForContext($context) ?? $tree->modelForContext(Str::singular($context));
        $class = $related !== null ? $related->fullyQualifiedClassName() : $tree->fqcnForContext(Str::studly(Str::singular($context)));
        $table = $related !== null ? $related->tableName() : Str::snake(Str::plural(Str::singular($context)));

        return new ForeignTarget(
            column: $name,
            class: ltrim($class, '\\'),
            model: $related,
            table: $table,
            key: $key,
            self: ltrim($class, '\\') === ltrim($model->fullyQualifiedClassName(), '\\'),
            nullable: ColumnProfile::from($column)->nullable,
            relation: Str::camel(Str::beforeLast($name, '_id')),
        );
    }
}
