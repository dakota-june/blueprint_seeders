<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Model;

/**
 * Where one foreign key column points.
 */
final class ForeignTarget
{
    public function __construct(
        public readonly string $column,
        public readonly string $class,
        public readonly ?Model $model,
        public readonly string $table,
        public readonly string $key,
        public readonly bool $self,
        public readonly bool $nullable,
        public readonly string $relation,
    ) {}
}
