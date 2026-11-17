<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Lexers;

use Blueprint\Contracts\Lexer;
use Dakotajune\Seeders\Support\SeedPlanParser;

/**
 * Takes the place of Blueprint's SeederLexer. It reads `seeders:` (the old comma list, and the new map), `factories:` and
 * the `fake:` tokens on column lines, and leaves the result in the tree for the generators.
 *
 * It does not touch `models:`, so it works next to Blueprint's ModelLexer and next to blueprint_models'
 * ModelOptionsLexer in either order: every lexer is handed the same raw draft.
 *
 * The `seeders` entry is still a list of model names, as Blueprint's own lexer left it, for anything that reads it.
 */
final class SeedPlanLexer implements Lexer
{
    public const KEY = 'seeders_plan';

    public function analyze(array $tokens): array
    {
        $plan = SeedPlanParser::parse($tokens);

        return [
            'seeders' => array_values(array_unique([...$plan->legacy, ...array_keys($plan->demo)])),
            self::KEY => $plan,
        ];
    }
}
