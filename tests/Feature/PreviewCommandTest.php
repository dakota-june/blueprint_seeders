<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

const PREVIEW_DRAFT = <<<'YAML'
models:
  Shelf:
    name: string:60 unique
  Book:
    title: string
    shelf_id: id
seeders:
  reference:
    Shelf: { rows: [Fiction, Travel] }
  demo:
    Book: { count: 4..6, for: { shelf: random } }
YAML;

function preview(array $options = []): array
{
    $code = Artisan::call('seeders:preview', ['draft' => 'draft.yaml'] + $options);

    return [$code, Artisan::output()];
}

it('shows what would be created and writes nothing', function () {
    $this->draft(PREVIEW_DRAFT);
    [$code, $output] = preview();

    expect($code)->toBe(0)
        ->and($output)->toContain('database/factories/BookFactory.php')
        ->toContain('database/seeders/Demo/BookSeeder.php')
        ->toContain('Seeded in this order: Shelf > Book')
        ->toContain('Book: 4 to 6 rows')
        ->and(file_exists(base_path('database/factories/BookFactory.php')))->toBeFalse();
});

it('reports a file as unchanged once it has been built', function () {
    $this->draft(PREVIEW_DRAFT);
    Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories,seeders']);

    expect(preview()[1])->toContain('unchanged');
});

it('shows a diff for a file that would change, and why a protected one would not', function () {
    $this->draft(PREVIEW_DRAFT);
    Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories']);
    $path = base_path('database/factories/BookFactory.php');
    file_put_contents($path, str_replace("'title'", "'headline'", (string) file_get_contents($path)));

    expect(preview(['--diff' => true])[1])->toContain('+++ database/factories/BookFactory.php')->toContain("-            'headline'");

    config(['seeders.overwrite' => 'protect']);
    $protected = preview()[1];

    expect($protected)->toContain('skip')->toContain('edited by hand');
});

it('limits itself to factories or seeders on request', function () {
    $this->draft(PREVIEW_DRAFT);

    expect(preview(['--only' => 'factories'])[1])->not->toContain('Seeder.php')
        ->and(preview(['--only' => 'seeders'])[1])->not->toContain('Factory.php');
});

it('exits 1 with the model and column when the draft cannot be generated', function () {
    $this->draft("models:\n  Node:\n    parent_id: id:node\n");
    [$code, $output] = preview();

    expect($code)->toBe(1)->and($output)->toContain('Node.parent_id');
});

it('exits 1 for a draft that is not there, and for an --only it does not know', function () {
    expect(Artisan::call('seeders:preview', ['draft' => 'nope.yaml']))->toBe(1);

    $this->draft(PREVIEW_DRAFT);

    expect(Artisan::call('seeders:preview', ['draft' => 'draft.yaml', '--only' => 'bogus']))->toBe(1);
});
