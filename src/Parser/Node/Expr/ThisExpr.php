<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class ThisExpr extends NodeBase implements Expr
{
    public function __construct(int $line, int $column)
    {
        parent::__construct($line, $column);
    }
}