<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Expr;

use PPhp\Parser\Node\NodeBase;

final class LiteralExpr extends NodeBase implements Expr
{
    public function __construct(
        int $line,
        int $column,
        public readonly mixed $value,
        public readonly string $kind, // 'int', 'float', 'string', 'bool', 'null'
    ) {
        parent::__construct($line, $column);
    }
}