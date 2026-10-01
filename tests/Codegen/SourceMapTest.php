<?php

declare(strict_types=1);

namespace PPhp\Tests\Codegen;

use PPhp\Codegen\MethodRegistry;
use PPhp\Codegen\PhpGenerator;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Runtime\SourceMapBuilder;
use PHPUnit\Framework\TestCase;

final class SourceMapTest extends TestCase
{
    private function generate(string $source): array
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($source, '<test>');
        $ast = $parser->parse($tokens, '<test>');

        $builder = new SourceMapBuilder();
        $generator = new PhpGenerator();
        $php = $generator->generate($ast, null, [], $builder);

        $sourceMap = $builder->build('<test>');
        return ['php' => $php, 'map' => $sourceMap];
    }

    public function testSingleStatement(): void
    {
        $result = $this->generate('<?pphp echo 42;');
        $php = $result['php'];
        $map = $result['map'];

        // Ligne 1 : <?php
        $this->assertSame(1, $map->sourceLine(1));
        // Ligne 2 : echo 42;
        $this->assertSame(1, $map->sourceLine(2)); // ligne 1 du PPHP (echo sur la même ligne que <?pphp)
    }

    public function testTwoStatements(): void
    {
        $result = $this->generate("<?pphp\necho 1;\necho 2;");
        $map = $result['map'];

        // Ligne 1 : <?php → source 1
        // Ligne 2 : echo 1; → source 2
        // Ligne 3 : echo 2; → source 3
        $this->assertSame(1, $map->sourceLine(1));
        $this->assertSame(2, $map->sourceLine(2));
        $this->assertSame(3, $map->sourceLine(3));
    }

    public function testIfBlock(): void
    {
        $result = $this->generate("<?pphp\nbool \$b = true;\nif (\$b) {\necho 1;\n}");
        $map = $result['map'];

        // Ligne 1 : <?php → 1
        // Ligne 2 : $b = true; → 2
        // Ligne 3 : if ($b) { → 3
        // Ligne 4 : echo 1; → 4
        // Ligne 5 : } → 5
        $this->assertSame(1, $map->sourceLine(1));
        $this->assertSame(2, $map->sourceLine(2));
        $this->assertSame(3, $map->sourceLine(3));
        $this->assertSame(4, $map->sourceLine(4));
    }

        public function testFunctionDecl(): void
    {
        $result = $this->generate("<?pphp\nfunction f(): void {\nreturn;\n}");
        $map = $result['map'];

        // Ligne 1 : <?php → 1
        // Ligne 2 : function f(): void { → 2
        // Ligne 3 : return; → 3
        // Ligne 4 : } → 2 (mappé à la ligne du function, car on ne connaît
        //            pas la ligne exacte du } de fermeture dans le source)
        $this->assertSame(2, $map->sourceLine(2));
        $this->assertSame(3, $map->sourceLine(3));
        $this->assertSame(2, $map->sourceLine(4));
    }

    public function testVarDeclWithType(): void
    {
        $result = $this->generate("<?pphp\nint \$x = 5;");
        $map = $result['map'];

        // Ligne 1 : <?php → 1
        // Ligne 2 : $x = 5; → 2
        $this->assertSame(2, $map->sourceLine(2));
    }
}