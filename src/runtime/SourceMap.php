<?php

declare(strict_types=1);

namespace PPhp\Runtime;

/**
 * Table de correspondance entre les lignes du code PHP généré
 * et les lignes du fichier .pphp source.
 *
 * Utilisée pour réécrire les erreurs runtime (fichier + ligne).
 */
final class SourceMap
{
    /** @var array<int,int> ligne générée => ligne source */
    private array $map = [];

    public function __construct(
        private readonly string $sourceFile,
    ) {}

    public function add(int $generatedLine, int $sourceLine): void
    {
        $this->map[$generatedLine] = $sourceLine;
    }

    public function sourceLine(int $generatedLine): ?int
    {
        return $this->map[$generatedLine] ?? null;
    }

    public function sourceFile(): string
    {
        return $this->sourceFile;
    }
}