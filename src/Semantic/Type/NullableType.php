<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

/**
 * ?T — nullable.
 * On ne crée pas de NullableType si T est déjà nullable ou null.
 */
final class NullableType implements Type
{
    public function __construct(public readonly Type $inner) {}

    public function __toString(): string
    {
        return '?' . $this->inner;
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self && $other->inner->equals($this->inner);
    }
}