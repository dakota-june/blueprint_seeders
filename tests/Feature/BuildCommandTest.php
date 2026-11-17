<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\DraftException;
use Illuminate\Support\Facades\Artisan;

const BUILD_DRAFT = <<<'YAML'
models:
  Category:
    name: string:60 unique
  Product:
    name: string
    category_id: id
seeders:
  reference:
    Category:
      rows: [Books, Games]
  demo:
    Product: 3
YAML;

it('builds factories and seeders with Blueprint\'s own command', function () {
    $this->draft(BUILD_DRAFT);

    expect(Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories,seeders']))->toBe(0);

    foreach (['database/factories/ProductFactory.php', 'database/seeders/Reference/CategorySeeder.php', 'database/seeders/Demo/ProductSeeder.php', 'database/seeders/DatabaseSeeder.php'] as $path) {
        expect(file_exists(base_path($path)))->toBeTrue("{$path} was not written")
            ->and(lints((string) file_get_contents(base_path($path))))->toBeTrue("{$path} does not parse");
    }
});

it('prints what it could not do after Blueprint\'s own list of files', function () {
    $this->draft("models:\n  Place:\n    area: point\n");

    Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories']);

    expect(Artisan::output())->toContain('seeders:')->toContain('Place.area');
});

it('stops with the model and column when the draft cannot be built', function () {
    $this->draft("models:\n  Node:\n    parent_id: id:node\n");

    expect(fn () => Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories']))
        ->toThrow(DraftException::class, 'Node.parent_id');
});

it('lets blueprint:erase remove what was generated and leave a protected file alone', function () {
    config(['seeders.overwrite' => 'protect']);
    $this->draft(BUILD_DRAFT);

    Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories']);
    $edited = base_path('database/factories/ProductFactory.php');
    file_put_contents($edited, file_get_contents($edited) . "\n// mine\n");

    Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories']);
    Artisan::call('blueprint:erase');

    expect(file_exists(base_path('database/factories/CategoryFactory.php')))->toBeFalse()
        ->and(file_exists($edited))->toBeTrue()
        ->and(file_get_contents($edited))->toContain('// mine');
});
