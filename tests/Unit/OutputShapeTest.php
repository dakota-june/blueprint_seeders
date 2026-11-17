<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\Decision;
use Dakotajune\Seeders\Support\OutputShape;

it('records [type, path] for a release whose build command reads that', function () {
    $out = [];
    OutputShape::record($out, new Decision('created', 'a.php'), 'Factory', typed: true);

    expect($out)->toBe(['created' => [['Factory', 'a.php']]]);
});

it('records a bare path for a release whose build command prints one', function () {
    $out = [];
    OutputShape::record($out, new Decision('created', 'a.php'), 'Factory', typed: false);

    expect($out)->toBe(['created' => ['a.php']]);
});

it('lists a rewritten file as created, as Blueprint does, so blueprint:erase can still remove it', function () {
    $out = [];

    foreach (['created', 'updated', 'unchanged'] as $action) {
        OutputShape::record($out, new Decision($action, "{$action}.php"), 'Seeder', typed: true);
    }

    expect($out['created'])->toHaveCount(3)->and($out)->not->toHaveKey('updated')->and($out)->not->toHaveKey('unchanged');
});

it('lists a skipped file as skipped, which erase leaves alone', function () {
    $out = [];
    OutputShape::record($out, new Decision('skipped', 'a.php', 'edited'), 'Factory', typed: true);

    expect($out)->toBe(['skipped' => [['Factory', 'a.php']]]);
});

it('detects the shape of the Blueprint that is installed', function () {
    // The oracle is the installed class itself: 2.10's create() records the path, later releases' does not.
    $source = file_get_contents((new ReflectionClass(Blueprint\Generators\AbstractClassGenerator::class))->getFileName());

    expect(OutputShape::typed())->toBe(! str_contains($source, "output['created']"));
});
