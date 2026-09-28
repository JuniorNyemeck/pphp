<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Semantic\Type\Type;

final class Symbol
{
    public function __construct(
        public readonly string $name,
        public readonly SymbolKind $kind,
        public readonly Type $type,
        public readonly int $line,
        public readonly int $column,
    ) {}
}