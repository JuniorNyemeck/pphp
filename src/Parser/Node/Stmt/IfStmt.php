<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\NodeBase;

final class IfStmt extends NodeBase implements Stmt
{
    /**
     * @param array<array{cond: Expr, body: Stmt}> $elseifs
     */
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $condition,
        public readonly Stmt $then,
        public readonly array $elseifs = [],
        public readonly ?Stmt $else = null,
    ) {
        parent::__construct($line, $column);
    }
}