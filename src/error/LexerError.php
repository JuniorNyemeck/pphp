<?php

declare(strict_types=1);

namespace PPhp\Error;

final class LexerError extends PPhpError
{
    public function __construct(string $message, ?string $file = null, ?int $line = null, ?int $column = null)
    {
        parent::__construct($message, $file, $line, $column, 'lexer error');
    }
}