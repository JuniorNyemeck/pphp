<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class IndexExpr extends NodeBase implements Expr
{
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $target,
        public readonly ?Expr $index, // null pour $a[]
    ) {
        parent::__construct($line, $column);
    }
}