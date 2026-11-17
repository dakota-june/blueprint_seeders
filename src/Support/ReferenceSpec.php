<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

final class ReferenceSpec
{
    /**
     * @param  list<ReferenceRow>  $rows
     */
    public function __construct(public readonly string $model, public readonly ?string $key, public readonly array $rows) {}
}
