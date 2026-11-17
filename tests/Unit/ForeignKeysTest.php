<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\ForeignKeys;

it('finds every foreign key of a model, and what it points at', function () {
    $tree = draft(FK_DRAFT);
    $keys = ForeignKeys::of($tree->models()['Contact'], $tree);

    expect(array_keys($keys))->toBe(['contact_type_id', 'company_id', 'parent_contact_id'])
        ->and($keys['contact_type_id']->model?->name())->toBe('ContactType')
        ->and($keys['contact_type_id']->table)->toBe('contact_types')
        ->and($keys['contact_type_id']->key)->toBe('id')
        ->and($keys['contact_type_id']->nullable)->toBeFalse()
        ->and($keys['contact_type_id']->relation)->toBe('contactType')
        ->and($keys['company_id']->nullable)->toBeTrue();
});

it('knows a self-reference, however the column names the model', function () {
    $tree = draft(FK_DRAFT);
    $keys = ForeignKeys::of($tree->models()['Contact'], $tree);

    expect($keys['parent_contact_id']->self)->toBeTrue()
        ->and($keys['parent_contact_id']->relation)->toBe('parentContact')
        ->and($keys['contact_type_id']->self)->toBeFalse();
});

it('follows Blueprint for a key that is not id, and for a table name', function () {
    $tree = draft(FK_DRAFT);
    $keys = ForeignKeys::of($tree->models()['Note'], $tree);

    expect($keys['owner_id']->model?->name())->toBe('User')
        ->and($keys['owner_id']->key)->toBe('id')
        ->and($keys['owner_uuid']->key)->toBe('uuid')
        ->and($keys['owner_uuid']->model?->name())->toBe('User');
});

it('still names the class when the related model is not in the draft', function () {
    $tree = draft(FK_DRAFT);
    $author = ForeignKeys::of($tree->models()['Note'], $tree)['author_id'];

    expect($author->model)->toBeNull()
        ->and($author->table)->toBe('authors')
        ->and($author->class)->toEndWith('\Author');
});

it('has none for a model with no foreign keys', function () {
    $tree = draft(FK_DRAFT);

    expect(ForeignKeys::of($tree->models()['ContactType'], $tree))->toBe([]);
});
