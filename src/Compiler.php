<?php

declare(strict_types=1);

namespace PPhp;

use PPhp\Runtime\SourceMap;

final class Compiler
{
    /**
     * Compile une source .pphp en code PHP.
     *
     * @return array{code: string, sourceMap: SourceMap}
     */
    public function compile(string $source, string $sourceFile): array
    {
        // Étape 0 : identity. Le vrai pipeline (lexer + parser + semantic + codegen)
        // viendra à l'étape 1.
        $sourceMap = new SourceMap($sourceFile);

        $lines = explode("\n", $source);
        foreach ($lines as $i => $_) {
            $sourceMap->add($i + 1, $i + 1);
        }

        $hierarchy = new ClassHierarchy($globals);
        $hierarchy->validate($sourceFile);

        return [
            'code' => $source,
            'sourceMap' => $sourceMap,
        ];
    }
}