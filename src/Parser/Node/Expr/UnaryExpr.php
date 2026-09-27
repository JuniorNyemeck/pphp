<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class UnaryExpr extends NodeBase implements Expr
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $op,   // '-', '!', '~'
        public readonly Expr $operand,
    ) {
        parent::__construct($line, $column);
    }
}