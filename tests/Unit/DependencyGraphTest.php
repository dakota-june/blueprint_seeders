<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\DependencyGraph;
use Dakotajune\Seeders\Support\DraftException;

it('orders models so that what a foreign key points at comes first', function () {
    $tree = draft(FK_DRAFT);

    $order = DependencyGraph::of($tree)->order();

    expect(array_search('ContactType', $order))->toBeLessThan(array_search('Contact', $order))
        ->and(array_search('Contact', $order))->toBeLessThan(array_search('Company', $order))
        ->and(array_search('User', $order))->toBeLessThan(array_search('Note', $order))
        ->and($order)->toHaveCount(5);
});

it('keeps the order of the draft where nothing says otherwise', function () {
    $tree = draft("models:\n  B:\n    n: string\n  A:\n    n: string\n  C:\n    n: string\n");

    expect(DependencyGraph::of($tree)->order())->toBe(['B', 'A', 'C']);
});

it('breaks a loop at a nullable key and says which', function () {
    $graph = DependencyGraph::of(draft(FK_DRAFT));

    expect($graph->isBroken('Contact', 'company_id'))->toBeTrue()
        ->and($graph->isBroken('Company', 'owner_contact_id'))->toBeFalse()
        ->and($graph->isBroken('Contact', 'contact_type_id'))->toBeFalse();
});

it('does not count a self-reference as a loop to break', function () {
    $graph = DependencyGraph::of(draft(FK_DRAFT));

    expect($graph->isBroken('Contact', 'parent_contact_id'))->toBeFalse();
});

it('refuses a loop of required keys and names every column in it', function () {
    $tree = draft("models:\n  A:\n    b_id: id:b\n  B:\n    a_id: id:a\n");

    expect(fn () => DependencyGraph::of($tree))
        ->toThrow(DraftException::class, 'A.b_id')
        ->and(fn () => DependencyGraph::of($tree))
        ->toThrow(DraftException::class, 'B.a_id');
});

it('lets the plan add its own dependencies, and refuses one that closes a loop', function () {
    $tree = draft("models:\n  Contact:\n    name: string\n  Tag:\n    name: string\n");
    $graph = DependencyGraph::of($tree);

    expect($graph->order())->toBe(['Contact', 'Tag'])
        ->and($graph->order(['Contact' => ['Tag']]))->toBe(['Tag', 'Contact'])
        ->and(fn () => $graph->order(['Contact' => ['Tag'], 'Tag' => ['Contact']]))
        ->toThrow(DraftException::class, 'Contact');
});

it('ignores keys that point outside the draft', function () {
    $tree = draft("models:\n  Post:\n    author_id: id:user\n");

    expect(DependencyGraph::of($tree)->order())->toBe(['Post']);
});
