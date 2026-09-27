<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class DotAccessExpr extends NodeBase implements Expr
{
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $target,
        public readonly string $member,
    ) {
        parent::__construct($line, $column);
    }
}