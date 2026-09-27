<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\NodeBase;

final class ExprStmt extends NodeBase implements Stmt
{
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $expr,
    ) {
        parent::__construct($line, $column);
    }
}