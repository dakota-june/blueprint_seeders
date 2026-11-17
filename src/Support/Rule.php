<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * One way to make a value: a PHP template in which `{f}` stands for the Faker generator (`$this->faker`, or
 * `$this->faker->unique()` when the column is unique), and what is known about what it produces.
 */
final class Rule
{
    /**
     * @param  ?int  $max  the longest text it typically produces, to compare with the column's length
     * @param  int  $min  the shortest text that is still a believable value of its kind
     * @param  bool  $truncatable  cutting it short still gives a value of the same kind (a name, not an email)
     * @param  ?int  $pool  how many different values it can produce, when that is a small number
     * @param  list<string>  $imports
     * @param  array<string, string>  $members
     */
    public function __construct(
        public readonly string $template,
        public readonly ?int $max = null,
        public readonly int $min = 0,
        public readonly bool $truncatable = false,
        public readonly string $what = 'value',
        public readonly ?int $pool = null,
        public readonly array $imports = [],
        public readonly array $members = [],
    ) {}

    public function render(bool $unique): string
    {
        return str_replace('{f}', $unique ? '$this->faker->unique()' : '$this->faker', $this->template);
    }

    public function withTemplate(string $template, ?int $max, ?int $pool = null): self
    {
        return new self($template, $max, $this->min, $this->truncatable, $this->what, $pool, $this->imports, $this->members);
    }
}
