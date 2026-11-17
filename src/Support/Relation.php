<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Model;

/**
 * A relationship a model declares that a seeder can fill: the method name it has on the model, what kind it is, the model
 * at the other end, and how the other end points back.
 */
final class Relation
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?Model $related,
        public readonly string $class,
        public readonly ?string $foreignKey = null,
        public readonly ?string $morph = null,
    ) {}

    public function isMany(): bool
    {
        return in_array($this->type, ['hasMany', 'morphMany', 'belongsToMany'], true);
    }
}
