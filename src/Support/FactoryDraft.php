<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

final class FactoryDraft
{
    /**
     * @param  array<string, string>  $guesses  column name pattern => faker expression
     * @param  array<string, ModelFactorySpec>  $models
     */
    public function __construct(public readonly array $guesses = [], public readonly array $models = []) {}
}
