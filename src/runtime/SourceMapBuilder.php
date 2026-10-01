<?php

declare(strict_types=1);

namespace PPhp\Runtime;

/**
 * Accumule les correspondances entre les lignes du code PHP généré
 * et les lignes du fichier .pphp source.
 *
 * Usage :
 *   - $builder->startStatement($sourceLine) avant de générer un statement
 *   - $builder->markLine() à chaque nouvelle ligne générée (appelé par writeln)
 */
final class SourceMapBuilder
{
    /** @var array<int, int>  ligne générée => ligne source */
    private array $map = [];

    private int $currentGeneratedLine = 0;
    private int $currentSourceLine = 1;

    /**
     * Indique la ligne source du statement en cours de génération.
     * Toutes les lignes générées jusqu'au prochain appel seront mappées à cette ligne.
     */
    public function startStatement(int $sourceLine): void
    {
        $this->currentSourceLine = $sourceLine;
    }

    /**
     * Marque une nouvelle ligne générée.
     * Appelé par le générateur à chaque writeln.
     */
    public function markLine(): void
    {
        $this->currentGeneratedLine++;
        $this->map[$this->currentGeneratedLine] = $this->currentSourceLine;
    }

    public function currentGeneratedLine(): int
    {
        return $this->currentGeneratedLine;
    }

    public function build(string $sourceFile): SourceMap
    {
        $sourceMap = new SourceMap($sourceFile);
        foreach ($this->map as $genLine => $srcLine) {
            $sourceMap->add($genLine, $srcLine);
        }
        return $sourceMap;
    }
}