<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Parser\Node\Param;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\TypeFactory;

final class FunctionInfo
{
    /**
     * @param Param[] $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly Type $returnType,
        public readonly int $line,
        public readonly int $column,
    ) {}

    public function signature(): string
    {
        $types = array_map(
            fn(Param $p) => (string) TypeFactory::fromNode($p->type),
            $this->params,
        );
        return $this->name . '(' . implode(',', $types) . ')';
    }
}