<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * The test that matters most: one draft becomes migrations, models, factories and seeders, which run on SQLite.
 *
 * Class names are used by this test alone, because PHP cannot unload a class once a generated file has been required.
 * The skeleton's own DatabaseSeeder is the same file the build overwrites and may already be loaded, so the two seeders
 * it would call are run directly and DatabaseSeeder is checked as text.
 */
const E2E_DRAFT = <<<'YAML'
models:
  Shelf:
    name: string:60 unique
  Book:
    title: string:120
    isbn: string:13 nullable unique
    price: decimal:8,2
    pages: unsignedSmallInteger
    status: enum:draft,live default:draft
    shelf_id: id
    relationships:
      hasMany: Review
  Review:
    rating: unsignedTinyInteger
    body: text nullable
    book_id: id
  Writer:
    name: string
    parent_writer_id: id:writer nullable

seeders:
  seed: 7
  reference:
    Shelf: { rows: [Fiction, Travel, Science] }
  demo:
    Book: { count: 12, for: { shelf: random }, has: { reviews: 1..3 } }
    Writer: 5
YAML;

function requireGenerated(): void
{
    $files = [
        ...glob(base_path('app/Models/{Shelf,Book,Review,Writer}.php'), GLOB_BRACE) ?: [],
        ...glob(base_path('database/factories/{Shelf,Book,Review,Writer}Factory.php'), GLOB_BRACE) ?: [],
        ...glob(base_path('database/seeders/Reference/*.php')) ?: [],
        ...glob(base_path('database/seeders/Demo/*.php')) ?: [],
        base_path('database/seeders/ReferenceSeeder.php'),
        base_path('database/seeders/DemoSeeder.php'),
    ];

    foreach ($files as $file) {
        require_once $file;
    }
}

function seedBoth(object $test): void
{
    $test->artisan('db:seed', ['--class' => 'Database\\Seeders\\ReferenceSeeder', '--no-interaction' => true])->assertExitCode(0);
    $test->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--no-interaction' => true])->assertExitCode(0);
}

/** @return array<string, mixed> */
function snapshotOfRows(): array
{
    return [
        'books' => App\Models\Book::query()->orderBy('id')->get(['title', 'isbn', 'price', 'pages', 'status', 'shelf_id'])->toArray(),
        'reviews' => App\Models\Review::query()->orderBy('id')->get(['rating', 'body', 'book_id'])->toArray(),
        'writers' => App\Models\Writer::query()->orderBy('id')->get(['name', 'parent_writer_id'])->toArray(),
    ];
}

beforeEach(function () {
    $this->draft(E2E_DRAFT);
    $this->artisan('blueprint:build', ['draft' => 'draft.yaml'])->assertExitCode(0);
    requireGenerated();
    $this->artisan('migrate')->assertExitCode(0);
});

it('seeds reference data that can be seeded again without duplicates', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ReferenceSeeder', '--no-interaction' => true])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ReferenceSeeder', '--no-interaction' => true])->assertExitCode(0);

    expect(App\Models\Shelf::query()->orderBy('id')->pluck('name')->all())->toBe(['Fiction', 'Travel', 'Science']);
});

it('seeds demo data that respects the draft: counts, foreign keys, enums, ranges', function () {
    seedBoth($this);

    $shelfIds = App\Models\Shelf::query()->pluck('id')->all();

    expect(App\Models\Book::count())->toBe(12)
        ->and(App\Models\Writer::count())->toBe(5)
        ->and(App\Models\Writer::query()->whereNotNull('parent_writer_id')->count())->toBe(0);

    foreach (App\Models\Book::all() as $book) {
        expect($shelfIds)->toContain($book->shelf_id)
            ->and(['draft', 'live'])->toContain($book->status)
            ->and((float) $book->price)->toBeLessThan(1000000.0)
            ->and($book->pages)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(65535)
            ->and($book->reviews()->count())->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
    }

    expect(App\Models\Book::query()->whereNotNull('isbn')->pluck('isbn')->unique()->count())
        ->toBe(App\Models\Book::query()->whereNotNull('isbn')->count());
});

it('makes the same demo rows every time it is run with the same seed', function () {
    seedBoth($this);
    $first = snapshotOfRows();

    $this->artisan('migrate:fresh')->assertExitCode(0);
    seedBoth($this);

    expect(snapshotOfRows())->toEqual($first);
});

it('gives a factory that cannot recurse, and states that fill what it leaves null', function () {
    expect(App\Models\Writer::factory()->create()->parent_writer_id)->toBeNull()
        ->and(App\Models\Writer::count())->toBe(1);

    $child = App\Models\Writer::factory()->withParentWriter()->create();

    expect(App\Models\Writer::count())->toBe(3)
        ->and($child->parent_writer_id)->not->toBeNull()
        ->and(App\Models\Book::factory()->live()->make()->status)->toBe('live');
});

it('makes unique values stay unique at volume', function () {
    expect(App\Models\Shelf::factory()->count(60)->make()->pluck('name')->unique()->count())->toBe(60);
});

it('writes a DatabaseSeeder that runs reference data everywhere and demo data only locally', function () {
    $source = (string) file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

    expect($source)->toContain('ReferenceSeeder::class')->toContain("app()->environment(['local', 'testing'])")->toContain('DemoSeeder::class');
});
