<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\NodeBase;

final class ForeachStmt extends NodeBase implements Stmt
{
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $iterable,
        public readonly Expr $value,
        public readonly ?Expr $key,
        public readonly Stmt $body,
    ) {
        parent::__construct($line, $column);
    }
}