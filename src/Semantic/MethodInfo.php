<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Parser\Node\Param;
use PPhp\Parser\Node\Stmt\MethodDeclStmt;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\TypeFactory;

final class MethodInfo
{
    /**
     * @param string[] $modifiers
     * @param Param[]  $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $modifiers,
        public readonly array $params,
        public readonly Type $returnType,
        public readonly ?MethodDeclStmt $ast,
        public readonly int $line,
        public readonly int $column,
    ) {}

    public function isPublic(): bool
    {
        return in_array('public', $this->modifiers, true);
    }

    public function isStatic(): bool
    {
        return in_array('static', $this->modifiers, true);
    }

    public function isAbstract(): bool
    {
        return in_array('abstract', $this->modifiers, true) || $this->ast?->body === null;
    }

    /**
     * Signature textuelle normalisée : nom + types de paramètres dans l'ordre.
     * Sert pour la détection de doublons.
     */
    public function signature(): string
    {
        $types = array_map(
            fn(Param $p) => (string) TypeFactory::fromNode($p->type),
            $this->params,
        );
        return $this->name . '(' . implode(',', $types) . ')';
    }
}