<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\NodeBase;

final class EchoStmt extends NodeBase implements Stmt
{
    /** @param Expr[] $expressions */
    public function __construct(
        int $line,
        int $column,
        public readonly array $expressions,
    ) {
        parent::__construct($line, $column);
    }
}