<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\NodeBase;

final class BreakStmt extends NodeBase implements Stmt
{
    public function __construct(int $line, int $column, public readonly ?int $level = null)
    {
        parent::__construct($line, $column);
    }
}