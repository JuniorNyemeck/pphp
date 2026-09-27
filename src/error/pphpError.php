<?php

declare(strict_types=1);

namespace PPhp\Error;


/**
 * Erreur de base pour toutes les erreurs PPHP.
 * Porte un fichier, une ligne, une colonne et un type (lexer, parser, type...).
 */
abstract class PPhpError extends \Exception
{
    public function __construct(
        string $message,
        private readonly ?string $sourceFile = null,
        private readonly ?int $sourceLine = null,
        private readonly ?int $sourceColumn = null,
        private readonly string $kind = 'error',
    ) {
        parent::__construct($message);
    }

    public function sourceFile(): ?string { return $this->sourceFile; }
    public function sourceLine(): ?int { return $this->sourceLine; }
    public function sourceColumn(): ?int { return $this->sourceColumn; }
    public function kind(): string { return $this->kind; }

    public function format(): string
    {
        $loc = '';
        if ($this->sourceFile !== null) {
            $loc = $this->sourceFile;
            if ($this->sourceLine !== null) {
                $loc .= ':' . $this->sourceLine;
                if ($this->sourceColumn !== null) {
                    $loc .= ':' . $this->sourceColumn;
                }
            }
            $loc = ' (' . $loc . ')';
        }
        return sprintf('pphp %s: %s%s', $this->kind, $this->getMessage(), $loc);
    }
}