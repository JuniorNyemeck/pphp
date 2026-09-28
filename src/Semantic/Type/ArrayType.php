<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

/**
 * T[] — tableau typé.
 * array sans paramètre est représenté par ArrayType avec element = MixedType.
 */
final class ArrayType implements Type
{
    public function __construct(public readonly Type $element) {}

    public function __toString(): string
    {
        return $this->element . '[]';
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self && $other->element->equals($this->element);
    }
}