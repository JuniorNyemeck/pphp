<?php

declare(strict_types=1);

namespace PPhp\Parser\Node;

use PPhp\Parser\Node\Expr\Expr;

final class Param extends NodeBase
{
    public function __construct(
        int $line,
        int $column,
        public readonly TypeNode $type,
        public readonly string $name,
        public readonly ?Expr $default,
        public readonly bool $byRef,
        public readonly bool $variadic,
    ) {
        parent::__construct($line, $column);
    }
}