<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\ColumnProfile;

it('reads length, uniqueness, nullability and default from a column', function () {
    $p = profile('string:60 unique nullable default:"Joe"', 'name');

    expect($p->name)->toBe('name')
        ->and($p->type)->toBe('string')
        ->and($p->length)->toBe(60)
        ->and($p->unique)->toBeTrue()
        ->and($p->nullable)->toBeTrue()
        ->and($p->default)->toBe('Joe');
});

it('has no length when none is written, and no default', function () {
    $p = profile('string');

    expect($p->length)->toBeNull()->and($p->default)->toBeNull()->and($p->unique)->toBeFalse()->and($p->nullable)->toBeFalse();
});

it('reads precision and scale, and the values of an enum', function () {
    $d = profile('decimal:8,2');
    $e = profile('enum:pending,active,"on hold"');

    expect($d->precision)->toBe(8)->and($d->scale)->toBe(2)
        ->and($e->values)->toBe(['pending', 'active', 'on hold']);
});

it('treats unsigned as a modifier or as part of the type, and reports the range', function () {
    expect(profile('unsignedSmallInteger')->unsigned)->toBeTrue()
        ->and(profile('integer unsigned')->unsigned)->toBeTrue()
        ->and(profile('unsignedSmallInteger')->range())->toBe([0, 65535])
        ->and(profile('smallInteger')->range())->toBe([-32768, 32767])
        ->and(profile('tinyInteger unsigned')->range())->toBe([0, 255])
        ->and(profile('unsignedMediumInteger')->range())->toBe([0, 16777215])
        ->and(profile('integer')->range())->toBe([-2147483648, 2147483647])
        ->and(profile('bigInteger')->range()[1])->toBe(PHP_INT_MAX);
});

it('knows keys and columns the database fills', function () {
    expect(profile('id', 'id')->isKey())->toBeTrue()
        ->and(profile('bigIncrements', 'x')->isKey())->toBeTrue()
        ->and(profile('string')->isKey())->toBeFalse()
        ->and(profile('timestamp useCurrent', 'seen_at')->dbFilled)->toBeTrue()
        ->and(profile('timestamp', 'seen_at')->dbFilled)->toBeFalse();
});

it('keeps the foreign key marker', function () {
    expect(profile('id foreign:users', 'owner_id')->foreign)->toBe('users')
        ->and(profile('unsignedBigInteger foreign', 'owner_id')->foreign)->toBe('foreign')
        ->and(profile('string')->foreign)->toBeNull();
});

it('is built from a Blueprint column only', function () {
    expect(profile('string'))->toBeInstanceOf(ColumnProfile::class);
});
