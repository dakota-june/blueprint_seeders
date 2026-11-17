<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

use Blueprint\Models\Column;

/**
 * What a factory needs to know about one column, read from Blueprint's own Column so it works on every release.
 * Blueprint keeps a column as a type, modifiers (`unique`, `['default' => 'x']`) and attributes (`string:60` is ['60'],
 * `decimal:8,2` is ['8', '2'], an enum is its values).
 */
final class ColumnProfile
{
    private const SPATIAL = ['geometry', 'geometrycollection', 'geography', 'linestring', 'multilinestring', 'multipoint', 'multipolygon', 'point', 'polygon'];

    /**
     * @param  list<string>  $values  enum and set values
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable,
        public readonly bool $unique,
        public readonly bool $unsigned,
        public readonly ?int $length,
        public readonly ?int $precision,
        public readonly ?int $scale,
        public readonly array $values,
        public readonly ?string $default,
        public readonly ?string $foreign,
        public readonly bool $dbFilled,
    ) {}

    public static function from(Column $column): self
    {
        $type = strtolower($column->dataType());
        $base = str_starts_with($type, 'unsigned') ? substr($type, 8) : $type;
        $attributes = array_values(array_map('strval', $column->attributes()));
        $number = fn (int $at): ?int => isset($attributes[$at]) && ctype_digit($attributes[$at]) ? (int) $attributes[$at] : null;

        $modifiers = [];
        $default = null;

        foreach ($column->modifiers() as $modifier) {
            if (is_array($modifier)) {
                foreach ($modifier as $key => $value) {
                    if ($key === 'default') {
                        $default = (string) $value;
                    }
                }
            } elseif (is_string($modifier)) {
                $modifiers[strtolower($modifier)] = true;
            }
        }

        $foreign = $column->isForeignKey();

        return new self(
            name: $column->name(),
            type: $type,
            nullable: isset($modifiers['nullable']) || str_starts_with($type, 'nullable') || ($default !== null && strtolower($default) === 'null'),
            unique: isset($modifiers['unique']),
            unsigned: isset($modifiers['unsigned']) || str_starts_with($type, 'unsigned'),
            length: in_array($base, ['string', 'char'], true) ? $number(0) : null,
            precision: in_array($base, ['decimal', 'float', 'double'], true) ? $number(0) : null,
            scale: in_array($base, ['decimal', 'float', 'double'], true) ? $number(1) : null,
            values: in_array($base, ['enum', 'set'], true) ? $attributes : [],
            default: $default,
            foreign: is_string($foreign) ? $foreign : null,
            dbFilled: isset($modifiers['usecurrent']) || isset($modifiers['autoincrement']),
        );
    }

    /** The type without `unsigned`: `unsignedsmallinteger` is `smallinteger`. */
    public function base(): string
    {
        return str_starts_with($this->type, 'unsigned') ? substr($this->type, 8) : $this->type;
    }

    public function isKey(): bool
    {
        return $this->name === 'id' || str_ends_with($this->type, 'increments');
    }

    public function isSpatial(): bool
    {
        return in_array($this->base(), self::SPATIAL, true);
    }

    public function isInteger(): bool
    {
        return in_array($this->base(), ['integer', 'biginteger', 'mediuminteger', 'smallinteger', 'tinyinteger'], true);
    }

    public function isDecimal(): bool
    {
        return in_array($this->base(), ['decimal', 'float', 'double'], true);
    }

    public function isTextual(): bool
    {
        return in_array($this->base(), ['string', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'], true);
    }

    public function isTemporal(): bool
    {
        return in_array($this->base(), ['date', 'datetime', 'datetimetz', 'timestamp', 'timestamptz', 'time', 'timetz', 'year'], true);
    }

    /**
     * The smallest and largest value the column holds. An unsigned bigint is capped at PHP_INT_MAX.
     *
     * @return array{0: int, 1: int}
     */
    public function range(): array
    {
        $bits = match ($this->base()) {
            'tinyinteger' => 8,
            'smallinteger' => 16,
            'mediuminteger' => 24,
            'integer' => 32,
            default => 64,
        };

        if ($bits === 64) {
            return [$this->unsigned ? 0 : PHP_INT_MIN, PHP_INT_MAX];
        }

        return $this->unsigned ? [0, (1 << $bits) - 1] : [-(1 << ($bits - 1)), (1 << ($bits - 1)) - 1];
    }
}
