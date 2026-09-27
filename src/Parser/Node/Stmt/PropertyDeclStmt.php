<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\NodeBase;
use PPhp\Parser\Node\TypeNode;

final class PropertyDeclStmt extends NodeBase implements Stmt
{
    /**
     * @param string[] $modifiers
     * @param array<array{name: string, init: ?Expr, line: int, column: int}> $declarators
     */
    public function __construct(
        int $line,
        int $column,
        public readonly array $modifiers,
        public readonly TypeNode $type,
        public readonly array $declarators,
    ) {
        parent::__construct($line, $column);
    }
}