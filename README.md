# dakotajune/seeders

Factories and seeders for [Laravel Blueprint](https://github.com/laravel-shift/blueprint) that you would keep. A drop-in: install it, and `blueprint:build` writes better ones. Nothing it generates needs this package at run time, so it can be a `--dev` dependency.

```bash
composer require --dev dakotajune/seeders
```

Works with Blueprint `^2.10` (checked against 2.10.0, 2.13.0 and master) and Laravel 11, 12 and 13. It sits next to [`dakotajune/blueprint_models`](https://github.com/dakotajune/blueprint_models) without touching `models:`, so the two work in either order.

## What it fixes

| Blueprint | This package |
|---|---|
| Value chosen from an exact list of 34 column names | Names and types: `billing_email`, `*_url`, `timezone`, `price`, `*_at`, and so on; yours with `fake:` or `factories: guesses:` |
| `string:20` ignored: an email or name can be longer than the column | The value is fitted to the column (cut, redrawn or lettered, whichever stays believable) |
| `unsignedSmallInteger` can overflow; signed integers go negative | Every integer stays inside its type's range |
| `unique` and `default:` ignored | `unique()` on Faker's chain; a default is used as the default |
| Nullable is all or nothing | Null some of the time (`null_chance`, 0.2 by default) |
| `X::factory()->create()->key` runs in `definition()`, a row per call | `X::factory()`, or a closure for a key that is not `id` |
| A self-reference recurses forever | Null by default, with a `withParent…()` state (named after the relationship); a required one is an error naming the column |
| Morphs get a random word as their type | A real class, drawn from the models that declare `morphMany`/`morphOne` |
| No states | `pending()`, `active()`, `verified()`/`unverified()`, `withCompany()`, and your own |
| `seeders:` is a comma string; a map is a `TypeError` | A list or a map; `reference:` and `demo:` |
| Seeder body is always `count(5)` | Counts, ranges, parents, children, states |
| No `DatabaseSeeder`; `Blog\Comment` seeder has the wrong namespace | Written, in foreign-key order; path and namespace match |
| Always overwrites | `always` (default, as Blueprint does), `protect` or `never` |

## A draft

```yaml
models:
  Shelf:
    name: string:60 unique
  Book:
    title: string:120
    isbn: string:13 nullable unique
    price: decimal:8,2
    status: enum:draft,live default:draft
    shelf_id: id
    relationships:
      hasMany: Review
  Review:
    rating: unsignedTinyInteger
    body: text nullable
    book_id: id

seeders:
  seed: 7                          # same rows every run
  reference:                       # lookup data: runs in production
    Shelf: { rows: [Fiction, Travel, Science] }
  demo:                            # local work only
    Book: { count: 12, for: { shelf: random }, has: { reviews: 1..3 } }
```

`php artisan blueprint:build` writes the factories and:

- `database/seeders/Reference/ShelfSeeder.php`: `updateOrCreate` on the key column, no Faker, safe to run again.
- `database/seeders/Demo/BookSeeder.php`: 12 books, each on an existing shelf, each with one to three reviews.
- `ReferenceSeeder`, `DemoSeeder`, and a `DatabaseSeeder` that runs reference data everywhere and demo data only in `local` and `testing`.

Look before you write: `php artisan seeders:preview` (`--diff`, `--only=factories|seeders`).

## Syntax

YAML dashes: Blueprint strips leading `-` from the whole draft, so block lists cannot be used. Everything here is a flow list `[a, b]` or a map.

**Column:** `fake:<Faker method>` on the line, no spaces inside: `score: integer fake:numberBetween(1,5)`. A method Faker does not have is an error that names the column and the nearest real one.

**`factories:`**

```yaml
factories:
  guesses:                         # every model
    "*_sku": "bothify('SKU-####-???')"
  Book:
    fake: { title: "sentence(4)" }
    states: { bestseller: { is_featured: true, rank: 1 }, fresh: { published_at: "=now()" } }   # "=" is PHP
    null_chance: 0.1
```

Precedence for one column: `fake:` on its line, then `factories: Model: fake:`, then `guesses` (draft, then config), then built-in name rules, then type rules.

**`seeders:`** The old `seeders: Post, Comment` still works (five demo rows each). The map form:

| Key | Meaning |
|---|---|
| `seed` | Integer. `DemoSeeder` seeds Faker with it. |
| `reference: Model: { key, rows }` | Fixed rows, matched on `key` (default: the first unique column, else `name`, `slug`, `code`, `key`, `title`). `rows` is a list of key values, or a map of key to columns. |
| `demo: Model: N` or `{ count, for, has, state }` | `count` is a number or a range `10..30`. `for: { rel: random }` picks an existing parent (`new` leaves it to the factory). `has: { rel: 1..3 }` makes children for each row; `belongsToMany` attaches existing rows. `state: [name]` applies factory states. |

`for`/`has` names are the model's relationships; a wrong one is an error listing the right ones.

## Overwrite policy

`config('seeders.overwrite')`, or `SEEDERS_OVERWRITE`:

- `always` (default, as Blueprint does).
- `protect`: every generated file starts with `// @generated by dakotajune/seeders [hash:...]`. A file whose body still matches is regenerated; an edited one, or one this package did not write, is **skipped** and reported. Line endings are ignored when comparing, so Windows or `autocrlf` do not look like edits. Delete the line to take a file over for good.
- `never`: only create what is missing.

Your own `DatabaseSeeder` is never overwritten whatever this says; Laravel's stock one is replaced. When yours is kept, the lines to add are printed. Skipped files are listed as `skipped`, so `blueprint:erase` does not delete them.

## Repeatable demo data

Set `seed` (draft or `SEEDERS_SEED`) and `anchor_date`. `DemoSeeder` seeds Faker, forgets the unique values already handed out (otherwise a second run in the same process makes different rows), and pins `now()` while it runs. Picks go through Faker over an ordered query, not `inRandomOrder()`. **Not covered by a seed:** `Str::random()`, Laravel's own uuids/ulids, `random_int()`.

## Config

`config/seeders.php`, every key documented in the file: `overwrite`, `dry_run` (`SEEDERS_DRY_RUN`), `seed`, `anchor_date`, `demo_environments`, `factories.null_chance`, `factories.guesses`, `docblocks`. A partly published file keeps the defaults of the keys it leaves out.

## What it does not do (yet)

Per-state fractions, pivot-table extra columns, per-column locales, JSON shapes beyond `[]`, spatial columns (a note; left out), cross-run uniqueness against rows already in the table. A unique value cannot also be cut to a short column (Faker's `unique()` and `valid()` do not compose), so that case is a warning, not a fix.

## Development

```bash
composer install
./vendor/bin/pest
```

On Windows PowerShell the same: `./vendor/bin/pest`. Generated files use `PHP_EOL`, and the tests normalise line endings.

Blueprint matrix (the output shape of `created` differs by release):

```bash
composer require --dev laravel-shift/blueprint:2.10.0 -W && ./vendor/bin/pest
composer require --dev laravel-shift/blueprint:2.13.0 -W && ./vendor/bin/pest
composer require --dev laravel-shift/blueprint:dev-master -W && ./vendor/bin/pest
```

`tests/Unit` is framework-light and covers the logic; `tests/Feature` runs Blueprint's own commands on Orchestra Testbench, including an end-to-end run on SQLite and a contract test that checks factory values against the rules `blueprint_models` infers.
