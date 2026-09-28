<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

final class NullType implements Type
{
    public function __toString(): string
    {
        return 'null';
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self;
    }
}