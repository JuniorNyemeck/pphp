<?php

declare(strict_types=1);

namespace PPhp;

use PPhp\Runtime\SourceMap;
use PPhp\Semantic\ClassHierarchy;

final class Compiler
{
    /**
     * Compile une source .pphp en code PHP.
     *
     * @return array{code: string, sourceMap: SourceMap}
     */
        public function compile(string $source, string $sourceFile): array
    {
        // 1. Lexer
        $lexer = new \PPhp\Lexer\Lexer();
        $tokens = $lexer->tokenize($source, $sourceFile);

        // 2. Parser
        $parser = new \PPhp\Parser\Parser();
        $ast = $parser->parse($tokens, $sourceFile);

        // 3. Collecte
        $collector = new \PPhp\Semantic\Collector();
        $globals = $collector->collect($ast, $sourceFile);

        // 4. Vérification de la hiérarchie
        $hierarchy = new \PPhp\Semantic\ClassHierarchy($globals);
        $hierarchy->validate($sourceFile);

        // 5. Vérification de types
        $typeChecker = new \PPhp\Semantic\TypeChecker($globals);
        $typeChecker->check($ast, $sourceFile);

        // 6. Codegen
        $generator = new \PPhp\Codegen\PhpGenerator();
        $phpCode = $generator->generate($ast);

        // 7. Source map (triviale pour l'instant : 1↔1)
        $sourceMap = new SourceMap($sourceFile);
        $lines = explode("\n", $source);
        foreach ($lines as $i => $_) {
            $sourceMap->add($i + 1, $i + 1);
        }

        return [
            'code' => $phpCode,
            'sourceMap' => $sourceMap,
        ];
    }
}