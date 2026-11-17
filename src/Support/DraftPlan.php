<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * Everything the draft says about factories and seeders, as written: checked for shape, not yet for meaning (whether
 * `Contact` is a model, whether `phones` is one of its relationships). That needs the models and is done when the
 * generators run.
 */
final class DraftPlan
{
    /**
     * @param  list<string>  $legacy  `seeders: Post, Comment`, Blueprint's own form
     * @param  array<string, ReferenceSpec>  $reference
     * @param  array<string, DemoSpec>  $demo
     * @param  array<string, array<string, string>>  $hints  model => column => faker expression, from `fake:` on a column line
     */
    public function __construct(
        public readonly ?int $seed = null,
        public readonly array $legacy = [],
        public readonly array $reference = [],
        public readonly array $demo = [],
        public readonly FactoryDraft $factories = new FactoryDraft(),
        public readonly array $hints = [],
    ) {}

    public function hasSeeders(): bool
    {
        return $this->legacy !== [] || $this->reference !== [] || $this->demo !== [];
    }
}
