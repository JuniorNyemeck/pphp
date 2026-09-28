<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

/**
 * Type d'une classe ou interface. Le nom est qualifié (\Foo\Bar, Foo, self, parent, static).
 * La résolution (self/parent/static → nom réel) se fait au moment de la collecte,
 * dans un contexte donné.
 */
final class ClassType implements Type
{
    public function __construct(public readonly string $name) {}

    public function __toString(): string
    {
        return $this->name;
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self && $other->name === $this->name;
    }
}