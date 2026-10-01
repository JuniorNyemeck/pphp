<?php

declare(strict_types=1);

namespace PPhp\Tests\Codegen;

use PPhp\Codegen\PhpGenerator;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class PhpGeneratorTest extends TestCase
{
    private function generate(string $source): string
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($source, '<test>');
        $ast = $parser->parse($tokens, '<test>');

        $generator = new PhpGenerator();
        return $generator->generate($ast);
    }

    // =========================================================================
    //  Base
    // =========================================================================

    public function testEmptyProgram(): void
    {
        $php = $this->generate('<?pphp ');
        $this->assertSame("<?php\n", $php);
    }

    public function testEchoString(): void
    {
        $php = $this->generate('<?pphp echo "hello";');
        $this->assertStringContainsString("echo 'hello';", $php);
    }

    public function testEchoInt(): void
    {
        $php = $this->generate('<?pphp echo 42;');
        $this->assertStringContainsString('echo 42;', $php);
    }

    public function testEchoMultiple(): void
    {
        $php = $this->generate('<?pphp echo "a", "b";');
        $this->assertStringContainsString("echo 'a', 'b';", $php);
    }

    public function testReturn(): void
    {
        $php = $this->generate('<?pphp return 5;');
        $this->assertStringContainsString('return 5;', $php);
    }

    public function testReturnVoid(): void
    {
        $php = $this->generate('<?pphp return;');
        $this->assertStringContainsString('return;', $php);
    }

    // =========================================================================
    //  Variables
    // =========================================================================

    public function testVariable(): void
    {
        $php = $this->generate('<?pphp int $a = 5;');
        $this->assertStringContainsString('$a = 5;', $php);
        // Le type 'int' ne doit PAS apparaître
        $this->assertStringNotContainsString('int $a', $php);
    }

    public function testVarDeclWithoutInit(): void
    {
        $php = $this->generate('<?pphp int $a;');
        $this->assertStringContainsString('$a;', $php);
    }

    public function testVarDeclMultiple(): void
    {
        $php = $this->generate('<?pphp int $a, $b = 2;');
        $this->assertStringContainsString('$a;', $php);
        $this->assertStringContainsString('$b = 2;', $php);
    }

    // =========================================================================
    //  Expressions
    // =========================================================================

    public function testBinaryAdd(): void
    {
        $php = $this->generate('<?pphp int $x = 1 + 2;');
        $this->assertStringContainsString('(1 + 2)', $php);
    }

    public function testConcatDot(): void
    {
        $php = $this->generate('<?pphp string $s = "a" . "b";');
        $this->assertStringContainsString("('a' . 'b')", $php);
    }

    public function testArithmeticWithParens(): void
    {
        $php = $this->generate('<?pphp int $x = 1 + 2 * 3;');
        // On veut (1 + (2 * 3)) ou équivalent
        $this->assertStringContainsString('*', $php);
        $this->assertStringContainsString('+', $php);
    }

    public function testUnaryMinus(): void
    {
        $php = $this->generate('<?pphp int $x = -5;');
        $this->assertStringContainsString('$x = -5;', $php);
    }

    public function testPreIncrement(): void
    {
        $php = $this->generate('<?pphp int $x = 0; ++$x;');
        $this->assertStringContainsString('++$x;', $php);
    }

    public function testPostIncrement(): void
    {
        $php = $this->generate('<?pphp int $x = 0; $x++;');
        $this->assertStringContainsString('$x++;', $php);
    }

    // =========================================================================
    //  Contrôle de flux
    // =========================================================================

    public function testIfSimple(): void
    {
        $php = $this->generate('<?pphp bool $b = true; if ($b) { echo "yes"; }');
        $this->assertStringContainsString('if ($b) {', $php);
        $this->assertStringContainsString("echo 'yes';", $php);
    }

    public function testIfElse(): void
    {
        $php = $this->generate('<?pphp bool $b = true; if ($b) { echo "y"; } else { echo "n"; }');
        $this->assertStringContainsString('if ($b) {', $php);
        $this->assertStringContainsString('} else {', $php);
    }

    public function testWhile(): void
    {
        $php = $this->generate('<?pphp bool $b = true; while ($b) { break; }');
        $this->assertStringContainsString('while ($b) {', $php);
        $this->assertStringContainsString('break;', $php);
    }

    public function testFor(): void
    {
        $php = $this->generate('<?pphp for (int $i = 0; $i < 10; $i++) { break; }');
        $this->assertStringContainsString('for ($i = 0; ($i < 10); $i++)', $php);
    }

    // =========================================================================
    //  Surcharges (reporté en 8b)
    // =========================================================================

    public function testNewObject(): void
    {
        $php = $this->generate('<?pphp class Foo {} Foo $f = new Foo();');
        // En 8a, les classes ne sont pas générées. On vérifie juste que
        // new Foo() est présent dans le code.
        $this->assertStringContainsString('new Foo()', $php);
    }

    public function testMethodCall(): void
    {
        $php = $this->generate('<?pphp class Foo { public function bar(): void {} } Foo $f = new Foo(); $f->bar();');
        $this->assertStringContainsString('$f->bar()', $php);
    }

    public function testDotAccess(): void
    {
        $php = $this->generate('<?pphp class Foo { public int $x; } Foo $f = new Foo(); int $y = $f.x;');
        $this->assertStringContainsString('$f->x', $php);
        // Le '.' doit être traduit en '->'
        $this->assertStringNotContainsString('$f.x', $php);
    }
}