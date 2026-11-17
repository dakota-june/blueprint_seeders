<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Faker\Factory;

/**
 * The faker methods a draft may name (`fake:safeEmail`), so a typo is caught when the draft is built and not when the
 * factory first runs. An open catalog accepts anything, for when Faker is not installed to ask.
 */
final class FormatterCatalog
{
    /** @var array<string, string> lower-case => as declared */
    private array $methods = [];

    /**
     * @param  list<string>  $methods
     */
    public function __construct(array $methods = [], private readonly bool $open = false)
    {
        foreach ($methods as $method) {
            $this->methods[strtolower($method)] = $method;
        }
    }

    public static function open(): self
    {
        return new self([], true);
    }

    /** @var array<string, self> */
    private static array $cache = [];

    public static function fromFaker(string $locale = 'en_US'): self
    {
        return self::$cache[$locale] ??= self::build($locale);
    }

    private static function build(string $locale): self
    {
        if (! class_exists(Factory::class)) {
            return self::open();
        }

        $methods = [];

        foreach (Factory::create($locale)->getProviders() as $provider) {
            foreach (get_class_methods($provider) as $method) {
                if (! str_starts_with($method, '__')) {
                    $methods[] = $method;
                }
            }
        }

        return new self(array_values(array_unique($methods)));
    }

    public function has(string $method): bool
    {
        return $this->open || isset($this->methods[strtolower($method)]);
    }

    /** The closest method name, when one is near enough to be what was meant. */
    public function suggest(string $method): ?string
    {
        $best = null;
        $distance = 4;

        foreach ($this->methods as $lower => $declared) {
            $d = levenshtein(strtolower($method), $lower);

            if ($d < $distance) {
                $best = $declared;
                $distance = $d;
            }
        }

        return $best;
    }
}
