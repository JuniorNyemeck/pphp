<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

final class ScalarType implements Type
{
    public const INT = 'int';
    public const FLOAT = 'float';
    public const BOOL = 'bool';
    public const STRING = 'string';

    private const VALID = [self::INT, self::FLOAT, self::BOOL, self::STRING];

    public function __construct(public readonly string $name)
    {
        if (!in_array($name, self::VALID, true)) {
            throw new \InvalidArgumentException("Type scalaire inconnu : {$name}");
        }
    }

    public static function int(): self { return new self(self::INT); }
    public static function float(): self { return new self(self::FLOAT); }
    public static function bool(): self { return new self(self::BOOL); }
    public static function string(): self { return new self(self::STRING); }

    public function __toString(): string
    {
        return $this->name;
    }

    public function equals(Type $other): bool
    {
        return $other instanceof self && $other->name === $this->name;
    }
}