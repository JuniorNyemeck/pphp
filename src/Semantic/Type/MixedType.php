<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

final class MixedType implements Type
{
    public function __toString(): string
    {
        return 'mixed';
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self;
    }
}