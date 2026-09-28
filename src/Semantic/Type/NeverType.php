<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

final class NeverType implements Type
{
    public function __toString(): string
    {
        return 'never';
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self;
    }
}