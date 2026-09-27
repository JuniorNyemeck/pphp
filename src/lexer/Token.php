<?php

declare(strict_types=1);

namespace PPhp\Lexer;

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $lexeme,
        public readonly mixed $value,
        public readonly int $line,
        public readonly int $column,
        public readonly bool $precededByWhitespace = false,
        public readonly bool $followedByWhitespace = false,
    ) {}

    public function __toString(): string
    {
        return sprintf(
            '%s(%s) @ %d:%d',
            $this->type->name,
            $this->lexeme,
            $this->line,
            $this->column,
        );
    }
}