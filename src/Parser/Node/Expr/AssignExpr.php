<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class AssignExpr extends NodeBase implements Expr
{
    public function __construct(
        int $line,
        int $column,
        public readonly string $op,   // '=', '+=', '-=', ...
        public readonly Expr $target,
        public readonly Expr $value,
    ) {
        parent::__construct($line, $column);
    }
}