<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\NodeBase;

final class ForStmt extends NodeBase implements Stmt
{
    /**
     * @param Expr[] $init
     * @param Expr[] $cond
     * @param Expr[] $step
     */
    public function __construct(
        int $line,
        int $column,
        public readonly array $init,
        public readonly array $cond,
        public readonly array $step,
        public readonly Stmt $body,
        public readonly ?VarDeclStmt $initDecl = null,
    ) {
        parent::__construct($line, $column);
    }
}