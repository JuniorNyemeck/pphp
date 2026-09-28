<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Semantic\Type\Type;

/**
 * Contexte d'analyse. Porte :
 *  - le scope courant (pour les variables locales)
 *  - la classe courante (pour $this, self, parent)
 *  - la méthode courante (pour $this, return type)
 *  - le type de retour attendu
 *  - le nombre de boucles englobantes (pour break/continue)
 */
final class Context
{
    public function __construct(
        public readonly Scope $scope,
        public readonly ?ClassInfo $currentClass = null,
        public readonly ?MethodInfo $currentMethod = null,
        public readonly ?Type $returnType = null,
        public readonly int $loopDepth = 0,
        public readonly bool $isStatic = false,
    ) {}

    public function withScope(Scope $scope): self
    {
        return new self(
            $scope,
            $this->currentClass,
            $this->currentMethod,
            $this->returnType,
            $this->loopDepth,
            $this->isStatic,
        );
    }

    public function withLoop(): self
    {
        return new self(
            $this->scope,
            $this->currentClass,
            $this->currentMethod,
            $this->returnType,
            $this->loopDepth + 1,
            $this->isStatic,
        );
    }

    public function withMethod(ClassInfo $class, MethodInfo $method): self
    {
        return new self(
            $this->scope,
            $class,
            $method,
            $method->returnType,
            $this->loopDepth,
            $method->isStatic(),
        );
    }

    public function withFunction(Type $returnType): self
    {
        return new self(
            $this->scope,
            null,
            null,
            $returnType,
            $this->loopDepth,
            false,
        );
    }

    public function inLoop(): bool
    {
        return $this->loopDepth > 0;
    }

    public function inMethod(): bool
    {
        return $this->currentMethod !== null;
    }

    public function canUseThis(): bool
    {
        return $this->inMethod() && !$this->isStatic;
    }
}