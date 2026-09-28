<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

final class VoidType implements Type
{
    public function __toString(): string
    {
        return 'void';
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self;
    }
}