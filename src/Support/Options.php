<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use InvalidArgumentException;

/**
 * `config('seeders')`, read once per build and checked, so a wrong value is reported with its key instead of surfacing
 * as odd output.
 */
final class Options
{
    /**
     * @param  list<string>  $environments
     * @param  array<string, string>  $guesses
     */
    public function __construct(
        public readonly string $overwrite = 'always',
        public readonly bool $dryRun = false,
        public readonly ?int $seed = null,
        public readonly ?string $anchor = null,
        public readonly array $environments = ['local', 'testing'],
        public readonly float $nullChance = 0.2,
        public readonly array $guesses = [],
        public readonly bool $docblocks = true,
    ) {
        if (! in_array($overwrite, ['always', 'protect', 'never'], true)) {
            throw new InvalidArgumentException("seeders.overwrite must be always, protect or never, not \"{$overwrite}\".");
        }

        if ($nullChance < 0.0 || $nullChance > 1.0) {
            throw new InvalidArgumentException("seeders.factories.null_chance must be between 0 and 1, not {$nullChance}.");
        }
    }

    public static function fromConfig(): self
    {
        $seed = config('seeders.seed');
        $anchor = config('seeders.anchor_date');
        $docblocks = config('seeders.docblocks');

        return new self(
            overwrite: (string) config('seeders.overwrite', 'always'),
            dryRun: (bool) config('seeders.dry_run', false),
            seed: $seed === null || $seed === '' ? null : (int) $seed,
            anchor: $anchor === null || $anchor === '' ? null : (string) $anchor,
            environments: array_values(array_map('strval', (array) config('seeders.demo_environments', ['local', 'testing']))),
            nullChance: (float) config('seeders.factories.null_chance', 0.2),
            guesses: (array) config('seeders.factories.guesses', []),
            docblocks: (bool) ($docblocks ?? config('blueprint.generate_phpdocs', true)),
        );
    }

    /** The factory settings: what the draft says first, then config. */
    public function settings(DraftPlan $plan): Settings
    {
        return new Settings($this->nullChance, [...$plan->factories->guesses, ...$this->guesses]);
    }
}
