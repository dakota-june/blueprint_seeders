<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Support\Str;

/**
 * The relationships of a model that a seeder can fill (`hasMany`, `hasOne`, `morphMany`, `morphOne`, `belongsToMany`),
 * under the method names Blueprint's model generator gives them, so `has: { phones: 1..3 }` means what the model says.
 */
final class Relations
{
    private const TYPES = ['hasMany', 'hasOne', 'morphMany', 'morphOne', 'belongsToMany'];

    /**
     * @return array<string, Relation> by method name
     */
    public static function of(Model $model, Tree $tree): array
    {
        $found = [];

        foreach (self::TYPES as $type) {
            foreach ($model->relationships()[$type] ?? [] as $reference) {
                $reference = (string) $reference;
                $custom = null;
                $foreign = $reference;

                if (str_contains($reference, ':')) {
                    [$foreign, $custom] = explode(':', $reference, 2);
                    $custom = str_starts_with($custom, '&') ? null : $custom;
                }

                $class = Str::studly(Str::afterLast(Str::before($foreign, '.'), '\\'));
                $related = $tree->modelForContext($class);
                $many = in_array($type, ['hasMany', 'morphMany', 'belongsToMany'], true);

                $base = $custom !== null ? Str::beforeLast($custom, '_id') : $class;
                // Blueprint master asks the model for its plural; the releases before it plural-ize the name.
                $plural = $custom === null && $related !== null && method_exists($related, 'pluralName') ? $related->pluralName() : Str::plural($base);
                $name = Str::camel($many ? $plural : $base);

                $found[$name] = new Relation(
                    name: $name,
                    type: $type,
                    related: $related,
                    class: $related !== null ? ltrim($related->fullyQualifiedClassName(), '\\') : ltrim($tree->fqcnForContext($class), '\\'),
                    foreignKey: in_array($type, ['hasMany', 'hasOne'], true) ? Str::snake($model->name()) . '_id' : null,
                    morph: str_starts_with($type, 'morph') ? Str::lower(Str::singular($class)) . 'able' : null,
                );
            }
        }

        return $found;
    }
}
