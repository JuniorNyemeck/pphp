<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class PropertyAccessExpr extends NodeBase implements Expr
{
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $target,
        public readonly string $property,
    ) {
        parent::__construct($line, $column);
    }
}