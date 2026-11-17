<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Tree;

/**
 * Which model needs which, by foreign key. It gives the order seeders run in, and decides where a loop between models
 * is cut: a loop (Contact.company_id, Company.owner_contact_id) is cut at a nullable key, which then defaults to null in
 * the factory. A loop of required keys has no place to start and is an error that names every column in it. A model
 * that points at itself is not a loop here; its factory handles that (nullable) or refuses it (required).
 */
final class DependencyGraph
{
    /**
     * @param  list<string>  $keys  the models, in the order of the draft
     * @param  array<string, list<array{to: string, column: string, nullable: bool}>>  $edges
     * @param  array<string, true>  $broken  `Model.column` of the nullable keys that close a loop
     */
    private function __construct(
        private readonly array $keys,
        private readonly array $edges,
        private readonly array $broken,
    ) {}

    public static function of(Tree $tree): self
    {
        $models = $tree->models();
        $byClass = [];

        foreach ($models as $key => $model) {
            $byClass[ltrim($model->fullyQualifiedClassName(), '\\')] = (string) $key;
        }

        $edges = [];

        foreach ($models as $key => $model) {
            foreach (ForeignKeys::of($model, $tree) as $foreignKey) {
                $to = $byClass[$foreignKey->class] ?? null;

                if ($to === null || $foreignKey->self) {
                    continue;
                }

                $edges[(string) $key][] = ['to' => $to, 'column' => $foreignKey->column, 'nullable' => $foreignKey->nullable];
            }
        }

        $keys = array_map('strval', array_keys($models));
        $component = self::components($keys, $edges);
        $broken = [];

        foreach ($edges as $from => $list) {
            foreach ($list as $edge) {
                if ($edge['nullable'] && $component[$from] === $component[$edge['to']]) {
                    $broken[$from . '.' . $edge['column']] = true;
                }
            }
        }

        $graph = new self($keys, $edges, $broken);
        $graph->assertNoRequiredLoop();

        return $graph;
    }

    public function isBroken(string $model, string $column): bool
    {
        return isset($this->broken[$model . '.' . $column]);
    }

    /**
     * Models in the order they can be seeded: what a key points at first, otherwise as in the draft.
     *
     * @param  array<string, list<string>>  $extra  more dependencies, `Model => [Model that must come first]`, from the seeder plan
     * @return list<string>
     */
    public function order(array $extra = []): array
    {
        $needs = array_fill_keys($this->keys, []);

        foreach ($this->edges as $from => $list) {
            foreach ($list as $edge) {
                if (! isset($this->broken[$from . '.' . $edge['column']])) {
                    $needs[$from][$edge['to']] = true;
                }
            }
        }

        foreach ($extra as $from => $firsts) {
            foreach ($firsts as $first) {
                if (isset($needs[$from], $needs[$first]) && $from !== $first) {
                    $needs[$from][$first] = true;
                }
            }
        }

        $placed = [];
        $remaining = $this->keys;

        while ($remaining !== []) {
            $next = null;

            foreach ($remaining as $key) {
                if (array_diff_key($needs[$key], $placed) === []) {
                    $next = $key;

                    break;
                }
            }

            if ($next === null) {
                $loop = implode(', ', array_map(fn (string $key): string => $key . ' needs ' . implode(' and ', array_keys(array_diff_key($needs[$key], $placed))), $remaining));

                throw DraftException::at($remaining[0], 'the models cannot be seeded in any order, because they wait for each other (' . $loop . ').', 'Remove a for:/has: from the seeder plan, or make a foreign key nullable.');
            }

            $placed[$next] = true;
            $remaining = array_values(array_filter($remaining, fn (string $key): bool => $key !== $next));
        }

        return array_keys($placed);
    }

    /** The models in the order they appear in the draft. */
    public function keys(): array
    {
        return $this->keys;
    }

    private function assertNoRequiredLoop(): void
    {
        $hard = [];

        foreach ($this->edges as $from => $list) {
            foreach ($list as $edge) {
                if (! isset($this->broken[$from . '.' . $edge['column']])) {
                    $hard[$from][] = $edge;
                }
            }
        }

        $state = [];
        $path = [];

        $visit = function (string $node) use (&$visit, &$state, &$path, $hard): void {
            $state[$node] = 1;

            foreach ($hard[$node] ?? [] as $edge) {
                $path[] = $node . '.' . $edge['column'] . ' -> ' . $edge['to'];

                if (($state[$edge['to']] ?? 0) === 1) {
                    $start = 0;

                    foreach ($path as $i => $step) {
                        if (str_ends_with(explode(' -> ', $step)[0], '') && str_starts_with($step, $edge['to'] . '.')) {
                            $start = $i;

                            break;
                        }
                    }

                    $loop = array_slice($path, $start);

                    throw DraftException::at(
                        explode(' -> ', $loop[0])[0],
                        'these required foreign keys point at each other, so nothing could be created first: ' . implode(', ', $loop) . '.',
                        'Make one of them nullable.',
                    );
                }

                if (($state[$edge['to']] ?? 0) === 0) {
                    $visit($edge['to']);
                }

                array_pop($path);
            }

            $state[$node] = 2;
        };

        foreach ($this->keys as $key) {
            if (($state[$key] ?? 0) === 0) {
                $visit($key);
            }
        }
    }

    /**
     * Tarjan's strongly connected components: models in the same one can all reach each other.
     *
     * @param  list<string>  $keys
     * @param  array<string, list<array{to: string, column: string, nullable: bool}>>  $edges
     * @return array<string, int> model => component number
     */
    private static function components(array $keys, array $edges): array
    {
        $index = 0;
        $stack = [];
        $on = [];
        $number = [];
        $low = [];
        $component = [];
        $count = 0;

        $connect = function (string $v) use (&$connect, &$index, &$stack, &$on, &$number, &$low, &$component, &$count, $edges): void {
            $number[$v] = $low[$v] = $index++;
            $stack[] = $v;
            $on[$v] = true;

            foreach ($edges[$v] ?? [] as $edge) {
                $w = $edge['to'];

                if (! isset($number[$w])) {
                    $connect($w);
                    $low[$v] = min($low[$v], $low[$w]);
                } elseif (! empty($on[$w])) {
                    $low[$v] = min($low[$v], $number[$w]);
                }
            }

            if ($low[$v] === $number[$v]) {
                do {
                    $w = array_pop($stack);
                    $on[$w] = false;
                    $component[$w] = $count;
                } while ($w !== $v);

                $count++;
            }
        };

        foreach ($keys as $key) {
            if (! isset($number[$key])) {
                $connect($key);
            }
        }

        return $component;
    }
}
