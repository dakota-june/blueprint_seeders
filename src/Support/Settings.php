<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * The factory settings that come from config and the draft's `factories:` section.
 */
final class Settings
{
    /**
     * @param  float  $nullChance  how often a nullable column is null (0 = never, 1 = always)
     * @param  array<string, string>  $guesses  column name pattern (`*_sku`) => faker expression, tried before the built-in names
     */
    public function __construct(
        public readonly float $nullChance = 0.2,
        public readonly array $guesses = [],
        public readonly ?FormatterCatalog $catalog = null,
    ) {}

    public function catalog(): FormatterCatalog
    {
        return $this->catalog ?? FormatterCatalog::fromFaker();
    }
}
