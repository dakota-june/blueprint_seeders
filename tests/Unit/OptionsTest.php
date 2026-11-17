<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\Options;

it('has working defaults', function () {
    $o = new Options();

    expect($o->overwrite)->toBe('always')
        ->and($o->dryRun)->toBeFalse()
        ->and($o->seed)->toBeNull()
        ->and($o->anchor)->toBeNull()
        ->and($o->environments)->toBe(['local', 'testing'])
        ->and($o->nullChance)->toBe(0.2)
        ->and($o->guesses)->toBe([])
        ->and($o->docblocks)->toBeTrue();
});

it('reads each key from config', function () {
    withConfig([
        'seeders.overwrite' => 'protect', 'seeders.dry_run' => true, 'seeders.seed' => '42',
        'seeders.anchor_date' => '2026-01-01', 'seeders.demo_environments' => ['local'],
        'seeders.factories.null_chance' => 0.5, 'seeders.factories.guesses' => ['*_sku' => 'ean13'],
        'seeders.docblocks' => false,
    ], function () {
        $o = Options::fromConfig();

        expect($o->overwrite)->toBe('protect')->and($o->dryRun)->toBeTrue()->and($o->seed)->toBe(42)
            ->and($o->anchor)->toBe('2026-01-01')->and($o->environments)->toBe(['local'])
            ->and($o->nullChance)->toBe(0.5)->and($o->guesses)->toBe(['*_sku' => 'ean13'])->and($o->docblocks)->toBeFalse();
    });
});

it('follows Blueprint\'s phpdoc setting when docblocks is not set', function () {
    withConfig(['seeders.docblocks' => null, 'blueprint.generate_phpdocs' => false], function () {
        expect(Options::fromConfig()->docblocks)->toBeFalse();
    });
});

it('says which key is wrong', function () {
    expect(fn () => new Options(nullChance: 1.5))->toThrow(InvalidArgumentException::class, 'seeders.factories.null_chance')
        ->and(fn () => new Options(overwrite: 'sometimes'))->toThrow(InvalidArgumentException::class, 'seeders.overwrite');
});
