<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\CountSpec;
use Dakotajune\Seeders\Support\DraftException;
use Dakotajune\Seeders\Support\SeedPlanParser;
use Symfony\Component\Yaml\Yaml;

function plan(string $yaml): Dakotajune\Seeders\Support\DraftPlan
{
    return SeedPlanParser::parse(Yaml::parse($yaml));
}

it('reads the old comma form as a list of models', function () {
    $p = plan('seeders: Post, Comment');

    expect($p->legacy)->toBe(['Post', 'Comment'])->and($p->demo)->toBe([])->and($p->reference)->toBe([]);
});

it('has an empty plan when the draft has no seeders or factories', function () {
    $p = plan("models:\n  Post:\n    title: string\n");

    expect($p->legacy)->toBe([])->and($p->seed)->toBeNull()->and($p->hasSeeders())->toBeFalse();
});

it('reads the seed, reference rows and demo volumes', function () {
    $p = plan(<<<'YAML'
    seeders:
      seed: 1234
      reference:
        ContactType:
          key: name
          rows: [Customer, Supplier]
        Country:
          key: code
          rows:
            US: { name: United States }
            CA: { name: Canada }
      demo:
        Contact:
          count: 20
          for: { contact_type: random }
          has: { phones: 1..3 }
          state: [vip]
        Tag: 10
    YAML);

    expect($p->seed)->toBe(1234)
        ->and($p->reference['ContactType']->key)->toBe('name')
        ->and(array_map(fn ($r) => $r->key, $p->reference['ContactType']->rows))->toBe(['Customer', 'Supplier'])
        ->and($p->reference['Country']->rows[1]->key)->toBe('CA')
        ->and($p->reference['Country']->rows[1]->attributes)->toBe(['name' => 'Canada'])
        ->and($p->demo['Contact']->count->min)->toBe(20)
        ->and($p->demo['Contact']->for)->toBe(['contact_type' => 'random'])
        ->and($p->demo['Contact']->has['phones']->min)->toBe(1)
        ->and($p->demo['Contact']->has['phones']->max)->toBe(3)
        ->and($p->demo['Contact']->states)->toBe(['vip'])
        ->and($p->demo['Tag']->count->max)->toBe(10)
        ->and($p->hasSeeders())->toBeTrue();
});

it('reads a row that carries its own key column', function () {
    $p = plan("seeders:\n  reference:\n    ContactType:\n      key: name\n      rows: [{ name: Customer, sort: 1 }, { name: Supplier, sort: 2 }]\n");

    expect($p->reference['ContactType']->rows[0]->key)->toBeNull()
        ->and($p->reference['ContactType']->rows[0]->attributes)->toBe(['name' => 'Customer', 'sort' => 1]);
});

it('reads counts and ranges', function () {
    expect(CountSpec::parse(20, 'x')->min)->toBe(20)
        ->and(CountSpec::parse('1..3', 'x')->max)->toBe(3)
        ->and(CountSpec::parse('0..4', 'x')->min)->toBe(0)
        ->and(fn () => CountSpec::parse('lots', 'seeders.demo.Contact.count'))->toThrow(DraftException::class, 'seeders.demo.Contact.count')
        ->and(fn () => CountSpec::parse('5..2', 'p'))->toThrow(DraftException::class, 'p')
        ->and(fn () => CountSpec::parse(-1, 'p'))->toThrow(DraftException::class, 'p');
});

it('names the path and the allowed options when something is misspelt', function () {
    expect(fn () => plan("seeders:\n  demo:\n    Contact:\n      cuont: 5\n"))
        ->toThrow(DraftException::class, 'seeders.demo.Contact')
        ->and(fn () => plan("seeders:\n  demo:\n    Contact:\n      cuont: 5\n"))
        ->toThrow(DraftException::class, 'count')
        ->and(fn () => plan("seeders:\n  demos: {}\n"))->toThrow(DraftException::class, 'seeders')
        ->and(fn () => plan("seeders:\n  reference:\n    T:\n      rows: Customer\n"))->toThrow(DraftException::class, 'seeders.reference.T.rows')
        ->and(fn () => plan("seeders:\n  seed: soon\n"))->toThrow(DraftException::class, 'seeders.seed');
});

it('reads inline fake: hints from the raw model columns, and ignores everything else', function () {
    $p = SeedPlanParser::parse(['models' => [
        'Contact' => [
            'score' => 'integer fake:numberBetween(1,5)',
            'nick' => 'string nullable fake:"userName"',
            'name' => 'string',
            'timestamps' => true,
            'relationships' => ['hasMany' => 'Phone'],
            'meta' => ['table' => 'contacts'],
        ],
    ]]);

    expect($p->hints)->toBe(['Contact' => ['score' => 'numberBetween(1,5)', 'nick' => 'userName']]);
});

it('reads the factories section', function () {
    $p = plan(<<<'YAML'
    factories:
      guesses:
        "*_sku": "bothify('SKU-####-???')"
      Contact:
        fake:
          bio: "paragraph(2)"
        states:
          vip: { is_vip: true, tier: gold }
        null_chance: 0.1
    YAML);

    expect($p->factories->guesses)->toBe(['*_sku' => "bothify('SKU-####-???')"])
        ->and($p->factories->models['Contact']->fake)->toBe(['bio' => 'paragraph(2)'])
        ->and($p->factories->models['Contact']->states)->toBe(['vip' => ['is_vip' => true, 'tier' => 'gold']])
        ->and($p->factories->models['Contact']->nullChance)->toBe(0.1);
});

it('names the model when a factories entry is wrong', function () {
    expect(fn () => plan("factories:\n  Contact:\n    null_chance: 3\n"))->toThrow(DraftException::class, 'factories.Contact.null_chance')
        ->and(fn () => plan("factories:\n  Contact:\n    states:\n      vip: yes please\n"))->toThrow(DraftException::class, 'factories.Contact.states.vip')
        ->and(fn () => plan("factories:\n  Contact:\n    fakes: {}\n"))->toThrow(DraftException::class, 'factories.Contact');
});
