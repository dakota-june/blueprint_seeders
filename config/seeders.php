<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | What to do with a file that is already there
    |--------------------------------------------------------------------------
    |
    | always   Overwrite it, as Blueprint does. (default)
    | protect  Overwrite only files this package wrote and nobody has edited since; skip the rest and say why.
    |          Every generated file carries a `@generated ... [hash:...]` header line that makes this possible.
    |          Delete that line to adopt a file for good.
    | never    Only create files that are not there.
    |
    | Your own DatabaseSeeder is never overwritten, whatever this says; Laravel's stock one is replaced.
    */
    'overwrite' => env('SEEDERS_OVERWRITE', 'always'),

    /*
    | Write nothing: `blueprint:build` still lists the files, and says nothing was written. `php artisan seeders:preview`
    | shows the same with a diff and does not need this.
    */
    'dry_run' => (bool) env('SEEDERS_DRY_RUN', false),

    /*
    |--------------------------------------------------------------------------
    | Repeatable demo data
    |--------------------------------------------------------------------------
    |
    | seed         An integer: DemoSeeder seeds Faker with it, so every run makes the same rows. null leaves the data
    |              random. A `seeders: seed:` in the draft wins over this.
    | anchor_date  A date such as '2026-01-01': DemoSeeder pins now() to it while it runs, so dates made relative to
    |              "now" repeat as well. null leaves them moving with the clock.
    |
    | What a seed does not cover: Str::random(), uuids and ulids made by Laravel, random_int() and inRandomOrder().
    */
    'seed' => env('SEEDERS_SEED'),

    'anchor_date' => env('SEEDERS_ANCHOR_DATE'),

    /*
    | The environments in which DatabaseSeeder also runs DemoSeeder. Reference data runs everywhere.
    */
    'demo_environments' => ['local', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Factories
    |--------------------------------------------------------------------------
    |
    | null_chance  How often a nullable column is null, from 0 (never) to 1 (always). A model can set its own under
    |              `factories: Model: null_chance:` in the draft.
    | guesses      Column name pattern => Faker expression, tried before the built-in rules:
    |                  '*_sku' => "bothify('SKU-####-???')"
    |              The draft's own `factories: guesses:` and `fake:` on a column come first.
    */
    'factories' => [
        'null_chance' => 0.2,
        'guesses' => [],
    ],

    /*
    | Docblocks on what is generated. null follows config('blueprint.generate_phpdocs').
    */
    'docblocks' => null,

];
