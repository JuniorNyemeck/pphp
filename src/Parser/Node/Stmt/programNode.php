<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\NodeBase;

final class ProgramNode extends NodeBase
{
    /** @param Stmt[] $statements */
    public function __construct(
        int $line,
        int $column,
        public readonly array $statements,
    ) {
        parent::__construct($line, $column);
    }
}