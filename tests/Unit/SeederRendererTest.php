<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\DraftException;

const SHOP = <<<'YAML'
models:
  Category:
    name: string:60 unique
    relationships:
      hasMany: Product
  Product:
    name: string
    category_id: id
    relationships:
      hasMany: Review
      belongsToMany: Tag
  Review:
    body: text
    product_id: id
  Tag:
    name: string:30 unique
  Image:
    path: string
    imageable: morphs
  Banner:
    title: string
    relationships:
      morphMany: Image

seeders:
  reference:
    Category:
      rows: [Books, Games]
  demo:
    Product:
      count: 20
      for: { category: random }
      has: { reviews: 1..3 }
YAML;

it('writes nothing when the draft plans no seeding', function () {
    expect(seeders("models:\n  Post:\n    title: string\n"))->toBe([]);
});

it('seeds reference data by matching the key column, so it can run again and again in production', function () {
    $s = seeders(SHOP)['database/seeders/Reference/CategorySeeder.php']->source;

    expect($s)->toContain('namespace Database\Seeders\Reference;')
        ->toContain('class CategorySeeder extends Seeder')
        ->toContain("['name' => 'Books']")
        ->toContain("['name' => 'Games']")
        ->toContain('updateOrCreate')
        ->toContain('unguarded')
        ->not->toContain('faker')
        ->not->toContain('factory()')
        ->and(lints($s))->toBeTrue();
});

it('takes the key column from the draft, from a unique column, or from a name-like column, and says when it cannot', function () {
    $explicit = "models:\n  Country:\n    code: string:2\n    name: string\nseeders:\n  reference:\n    Country:\n      key: code\n      rows:\n        US: { name: United States }\n";
    $s = seeders($explicit)['database/seeders/Reference/CountrySeeder.php']->source;

    expect($s)->toContain("'code' => 'US'")->toContain("'name' => 'United States'");

    $none = "models:\n  Thing:\n    weight: integer\nseeders:\n  reference:\n    Thing:\n      rows: [a]\n";

    expect(fn () => seeders($none))->toThrow(DraftException::class, 'Thing')
        ->and(fn () => seeders($none))->toThrow(DraftException::class, 'key');
});

it('refuses a reference row that gives no value for the key column', function () {
    $yaml = "models:\n  Country:\n    code: string:2 unique\n    name: string\nseeders:\n  reference:\n    Country:\n      rows: [{ name: Canada }]\n";

    expect(fn () => seeders($yaml))->toThrow(DraftException::class, 'Country');
});

it('refuses a seeder for a model the draft does not have, listing the ones it has', function () {
    $yaml = "models:\n  Post:\n    title: string\nseeders:\n  reference:\n    Nope:\n      rows: [a]\n";

    expect(fn () => seeders($yaml))->toThrow(DraftException::class, 'Nope')
        ->and(fn () => seeders($yaml))->toThrow(DraftException::class, 'Post');
});

it('seeds a demo model through its factory, with a count', function () {
    $s = seeders(SHOP)['database/seeders/Demo/ProductSeeder.php']->source;

    expect($s)->toContain('namespace Database\Seeders\Demo;')
        ->toContain('Product::factory()')
        ->toContain('->count(20)')
        ->and(lints($s))->toBeTrue();
});

it('picks an existing parent in a repeatable way and says what is missing when there is none', function () {
    $s = seeders(SHOP)['database/seeders/Demo/ProductSeeder.php']->source;

    expect($s)->toContain("orderBy('id')")
        ->toContain('$faker->randomElement(')
        ->toContain('=== []')
        ->toContain('Product')
        ->toContain('Category')
        ->not->toContain('inRandomOrder');
});

it('gives each parent its own random number of children', function () {
    $s = seeders(SHOP)['database/seeders/Demo/ProductSeeder.php']->source;

    expect($s)->toContain('afterCreating')
        ->toContain('Review::factory()->count($faker->numberBetween(1, 3))')
        ->toContain("'product_id' => \$product->getKey()");
});

it('fills a morph child with both its id and its type', function () {
    $yaml = SHOP . "\n    Banner:\n      count: 3\n      has: { images: 2 }\n";
    $s = seeders($yaml)['database/seeders/Demo/BannerSeeder.php']->source;

    expect($s)->toContain("'imageable_id' => \$banner->getKey()")
        ->toContain("'imageable_type' => \$banner->getMorphClass()")
        ->toContain('->count(2)');
});

it('attaches belongsToMany from rows that already exist, never more than there are', function () {
    $yaml = SHOP . "\n    Tag: 5\n";
    $yaml = str_replace('has: { reviews: 1..3 }', 'has: { reviews: 1..3, tags: 1..2 }', $yaml);
    $s = seeders($yaml)['database/seeders/Demo/ProductSeeder.php']->source;

    expect($s)->toContain('->tags()->attach(')
        ->toContain('min(')
        ->toContain('count($tagIds)')
        ->and(lints($s))->toBeTrue();
});

it('applies the states the draft names, and refuses one the factory does not have', function () {
    $ok = "models:\n  Job:\n    status: enum:open,closed\nseeders:\n  demo:\n    Job:\n      count: 4\n      state: [open]\n";

    expect(seeders($ok)['database/seeders/Demo/JobSeeder.php']->source)->toContain('->open()');

    $bad = str_replace('[open]', '[shut]', $ok);

    expect(fn () => seeders($bad))->toThrow(DraftException::class, 'Job')
        ->and(fn () => seeders($bad))->toThrow(DraftException::class, 'open');
});

it('refuses a for: or has: that is not a relationship, listing the ones the model has', function () {
    expect(fn () => seeders(str_replace('reviews: 1..3', 'reviewz: 1..3', SHOP)))
        ->toThrow(DraftException::class, 'Product.has.reviewz')
        ->and(fn () => seeders(str_replace('reviews: 1..3', 'reviewz: 1..3', SHOP)))
        ->toThrow(DraftException::class, 'reviews');

    expect(fn () => seeders(str_replace('category: random', 'categry: random', SHOP)))
        ->toThrow(DraftException::class, 'categry');
});

it('treats the old comma list as demo seeders of five', function () {
    $s = seeders("models:\n  Post:\n    title: string\nseeders: Post\n");

    expect($s)->toHaveKey('database/seeders/Demo/PostSeeder.php')
        ->and($s['database/seeders/Demo/PostSeeder.php']->source)->toContain('->count(5)');
});

it('keeps path and namespace together for a model in a folder', function () {
    $s = seeders("models:\n  Blog\\Comment:\n    body: text\nseeders: Blog\\Comment\n")['database/seeders/Demo/Blog/CommentSeeder.php'];

    expect($s->source)->toContain('namespace Database\Seeders\Demo\Blog;')->and(lints($s->source))->toBeTrue();
});

it('runs the seeders in the order foreign keys need, whatever order the draft is in', function () {
    $yaml = "models:\n  Book:\n    shelf_id: id\n  Shelf:\n    name: string\nseeders: Book, Shelf\n";
    $s = seeders($yaml)['database/seeders/DemoSeeder.php']->source;
    $run = substr($s, (int) strpos($s, 'function run'));

    expect(strpos($run, 'ShelfSeeder'))->toBeLessThan(strpos($run, 'BookSeeder'));
});

it('runs reference data before anything else', function () {
    $files = seeders(SHOP);

    expect($files)->toHaveKeys(['database/seeders/ReferenceSeeder.php', 'database/seeders/DemoSeeder.php', 'database/seeders/DatabaseSeeder.php']);

    $db = $files['database/seeders/DatabaseSeeder.php']->source;

    expect($db)->toContain('$this->call(ReferenceSeeder::class);')
        ->toContain("app()->environment(['local', 'testing'])")
        ->toContain('$this->call(DemoSeeder::class);')
        ->and(strpos($db, 'ReferenceSeeder'))->toBeLessThan(strpos($db, 'DemoSeeder'))
        ->and($files['database/seeders/DatabaseSeeder.php']->guardHandWritten)->toBeTrue();
});

it('leaves out of DatabaseSeeder what the draft does not seed', function () {
    $onlyReference = "models:\n  Category:\n    name: string unique\nseeders:\n  reference:\n    Category:\n      rows: [a]\n";
    $files = seeders($onlyReference);

    expect($files)->not->toHaveKey('database/seeders/DemoSeeder.php')
        ->and($files['database/seeders/DatabaseSeeder.php']->source)->not->toContain('DemoSeeder')->toContain('ReferenceSeeder');
});

it('seeds Faker and pins the clock only when asked, and always puts the clock back', function () {
    $plain = seeders(SHOP)['database/seeders/DemoSeeder.php']->source;

    expect($plain)->not->toContain('seed(')->not->toContain('setTestNow');

    $pinned = seeders(SHOP, ['seed' => 7, 'anchor' => '2026-01-01'])['database/seeders/DemoSeeder.php']->source;

    expect($pinned)->toContain('$faker->seed(7);')
        ->toContain('$faker->unique(true);')
        ->toContain("Carbon::setTestNow('2026-01-01')")
        ->toContain('finally')
        ->and(strpos($pinned, 'finally'))->toBeGreaterThan(strpos($pinned, '$this->call('))
        ->and(lints($pinned))->toBeTrue();
});

it('lets the seed in the draft win over the one in config', function () {
    $yaml = str_replace("seeders:\n", "seeders:\n  seed: 99\n", SHOP);

    expect(seeders($yaml, ['seed' => 7])['database/seeders/DemoSeeder.php']->source)->toContain('$faker->seed(99);');
});

it('writes PHP that parses for every file of a whole draft', function () {
    foreach (seeders(SHOP) as $path => $file) {
        expect(lints($file->source))->toBeTrue("{$path} does not parse");
    }
});
