<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class ArrayLiteralExpr extends NodeBase implements Expr
{
    /** @param array<array{key: ?Expr, value: Expr}> $elements */
    public function __construct(
        int $line,
        int $column,
        public readonly array $elements,
    ) {
        parent::__construct($line, $column);
    }
}