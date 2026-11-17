<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * What happened to one file (or, in a dry run, what would): created, updated, unchanged, or skipped and why.
 */
final class Decision
{
    public function __construct(
        public readonly string $action,
        public readonly string $path,
        public readonly ?string $reason = null,
        public readonly ?string $old = null,
        public readonly ?string $new = null,
    ) {}
}
