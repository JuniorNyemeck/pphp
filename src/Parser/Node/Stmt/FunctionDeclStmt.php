<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\NodeBase;
use PPhp\Parser\Node\Param;
use PPhp\Parser\Node\TypeNode;

final class FunctionDeclStmt extends NodeBase implements Stmt
{
    /**
     * @param string[]  $modifiers  public, private, protected, static, abstract, final
     * @param Param[]   $params
     */
    public function __construct(
        int $line,
        int $column,
        public readonly array $modifiers,
        public readonly string $name,
        public readonly array $params,
        public readonly ?TypeNode $returnType,
        public readonly ?BlockStmt $body,   // null si abstract ou interface
    ) {
        parent::__construct($line, $column);
    }

    public function isAbstract(): bool
    {
        return in_array('abstract', $this->modifiers, true) || $this->body === null;
    }
}