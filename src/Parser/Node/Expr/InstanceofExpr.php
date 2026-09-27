<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class InstanceofExpr extends NodeBase implements Expr
{
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $operand,
        public readonly string $className,
    ) {
        parent::__construct($line, $column);
    }
}