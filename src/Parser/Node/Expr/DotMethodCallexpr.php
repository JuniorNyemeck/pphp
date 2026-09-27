<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class DotMethodCallExpr extends NodeBase implements Expr
{
    /** @param Expr[] $args */
    public function __construct(
        int $line,
        int $column,
        public readonly Expr $target,
        public readonly string $method,
        public readonly array $args,
    ) {
        parent::__construct($line, $column);
    }
}