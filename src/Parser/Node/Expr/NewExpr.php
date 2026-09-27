<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class NewExpr extends NodeBase implements Expr
{
    /** @param Expr[] $args */
    public function __construct(
        int $line,
        int $column,
        public readonly string $className,
        public readonly array $args,
    ) {
        parent::__construct($line, $column);
    }
}