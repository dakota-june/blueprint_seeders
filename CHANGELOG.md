# Changelog

## Unreleased

First version.

- Factories: values from column name and type, fitted to length and range; enums, unique, nullable, defaults; lazy foreign keys; cycle-safe self-references and loops; morphs; states; `fake:` and `factories:` overrides.
- Seeders: reference (idempotent, no Faker) and demo (counts, ranges, parents, children, states); foreign-key order; `DatabaseSeeder`.
- Repeatable demo data: seed, unique reset, anchor date.
- Overwrite policy `always` (default), `protect`, `never`, with a hash header; `DatabaseSeeder` of your own is never replaced.
- `seeders:preview` with `--diff` and `--only`.
- Checked against Blueprint 2.10.0, 2.13.0 and master.
