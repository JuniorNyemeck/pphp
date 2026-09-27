<?php

declare(strict_types=1);

namespace PPhp\Parser\Node;

abstract class NodeBase implements Node
{
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {}

    public function line(): int { return $this->line; }
    public function column(): int { return $this->column; }
}