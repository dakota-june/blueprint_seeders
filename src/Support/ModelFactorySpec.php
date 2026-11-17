<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

final class ModelFactorySpec
{
    /**
     * @param  array<string, string>  $fake  column => faker expression
     * @param  array<string, array<string, scalar|null>>  $states  name => attributes
     */
    public function __construct(
        public readonly array $fake = [],
        public readonly array $states = [],
        public readonly ?float $nullChance = null,
    ) {}
}
