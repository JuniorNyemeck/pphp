<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\NodeBase;
use PPhp\Parser\Node\Param;
use PPhp\Parser\Node\TypeNode;

final class MethodDeclStmt extends NodeBase implements Stmt
{
    /**
     * @param string[] $modifiers
     * @param Param[]  $params
     */
    public function __construct(
        int $line,
        int $column,
        public readonly array $modifiers,
        public readonly string $name,
        public readonly array $params,
        public readonly ?TypeNode $returnType,
        public readonly ?BlockStmt $body,
    ) {
        parent::__construct($line, $column);
    }

    public function isAbstract(): bool
    {
        return in_array('abstract', $this->modifiers, true) || $this->body === null;
    }

    public function isStatic(): bool
    {
        return in_array('static', $this->modifiers, true);
    }
}