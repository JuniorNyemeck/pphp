<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\NodeBase;
use PPhp\Parser\Node\TypeNode;

/**
 * Déclaration typée : int $a;  /  T $a = expr;  /  int $a, $b = 2;
 */
final class VarDeclStmt extends NodeBase implements Stmt
{
    /**
     * @param array<array{name: string, init: ?Expr, line: int, column: int}> $declarators
     */
    public function __construct(
        int $line,
        int $column,
        public readonly TypeNode $type,
        public readonly array $declarators,
    ) {
        parent::__construct($line, $column);
    }
}