<?php

declare(strict_types=1);

namespace PPhp\Error;

class ParserError extends PPhpError
{
    public function __construct(string $message, ?string $file = null, ?int $line = null, ?int $column = null)
    {
        parent::__construct($message, $file, $line, $column, 'syntax error');
    }
}