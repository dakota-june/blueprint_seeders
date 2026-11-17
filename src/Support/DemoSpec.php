<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

final class DemoSpec
{
    /**
     * @param  array<string, string>  $for  belongsTo relation => `random` (an existing row) or `new` (made by its factory)
     * @param  array<string, CountSpec>  $has  hasMany/morphMany/belongsToMany relation => how many per parent
     * @param  list<string>  $states  factory states applied to every row
     */
    public function __construct(
        public readonly string $model,
        public readonly CountSpec $count,
        public readonly array $for = [],
        public readonly array $has = [],
        public readonly array $states = [],
    ) {}
}
