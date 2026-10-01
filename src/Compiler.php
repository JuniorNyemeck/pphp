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

    // 6. Conversion de la table de résolution en noms mangleds
    $registry = new \PPhp\Codegen\MethodRegistry($globals);
    $resolvedNames = [];
    foreach ($typeChecker->resolvedCalls() as $key => $info) {
        if ($info instanceof \PPhp\Semantic\MethodInfo) {
            $overloads = $globals->getClass($info->declaringClass)?->methods[$info->name] ?? [];
            $index = array_search($info, $overloads, true);
            if ($index !== false) {
                $resolvedNames[$key] = $registry->methodName(
                    $info->declaringClass,
                    $info->name,
                    $index,
                );
            }
        } elseif ($info instanceof \PPhp\Semantic\FunctionInfo) {
            $overloads = $globals->getFunctions($info->name);
            $index = array_search($info, $overloads, true);
            if ($index !== false) {
                $resolvedNames[$key] = $registry->functionName($info->name, $index);
            }
        }
    }

    // 7. Codegen
            // 7. Codegen avec source map
        $sourceMapBuilder = new \PPhp\Runtime\SourceMapBuilder();
        $generator = new \PPhp\Codegen\PhpGenerator();
        $phpCode = $generator->generate($ast, $registry, $resolvedNames, $sourceMapBuilder);

        // 8. Construction de la source map finale
        $sourceMap = $sourceMapBuilder->build($sourceFile);

        return [
            'code' => $phpCode,
            'sourceMap' => $sourceMap,
        ];
}
}