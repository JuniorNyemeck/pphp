<?php

declare(strict_types=1);

namespace PPhp\Parser\Node\Stmt;

use PPhp\Parser\Node\NodeBase;

final class ClassDeclStmt extends NodeBase implements Stmt
{
    /**
     * @param string[] $modifiers  abstract, final, readonly
     * @param string[] $implements
     * @param Stmt[]   $members    PropertyDeclStmt | MethodDeclStmt
     */
    public function __construct(
        int $line,
        int $column,
        public readonly array $modifiers,
        public readonly string $name,
        public readonly ?string $extends,
        public readonly array $implements,
        public readonly array $members,
        public readonly bool $isInterface = false,
    ) {
        parent::__construct($line, $column);
    }
}