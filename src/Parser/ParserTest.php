<?php

declare(strict_types=1);

namespace PPhp\Tests\Parser;

use PPhp\Error\ParserError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\AstPrinter;
use PPhp\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private Lexer $lexer;
    private Parser $parser;
    private AstPrinter $printer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->printer = new AstPrinter();
    }

    private function parse(string $source): string
    {
        $tokens = $this->lexer->tokenize($source);
        $ast = $this->parser->parse($tokens);
        return $this->printer->print($ast);
    }

    private function assertParseError(string $source, string $messagePart): void
    {
        try {
            $this->parse($source);
            $this->fail('ParserError attendue');
        } catch (ParserError $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // =========================================================================
    //  Programme vide
    // =========================================================================

    public function testEmptyProgram(): void
    {
        $this->assertSame("Program", $this->parse('<?php '));
    }

    // =========================================================================
    //  Echo
    // =========================================================================

    public function testEchoString(): void
    {
        $expected = <<<'TXT'
        Program
          Echo
            Literal(string, 'hello')
        TXT;
        $this->assertSame($expected, $this->parse('<?php echo "hello";'));
    }

    public function testEchoMultiple(): void
    {
        $expected = <<<'TXT'
        Program
          Echo
            Literal(string, 'a')
            Literal(string, 'b')
        TXT;
        $this->assertSame($expected, $this->parse('<?php echo "a", "b";'));
    }

    // =========================================================================
    //  Littéraux
    // =========================================================================

    public function testIntLiteral(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Literal(int, 42)
        TXT;
        $this->assertSame($expected, $this->parse('<?php 42;'));
    }

    public function testFloatLiteral(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Literal(float, 3.14)
        TXT;
        $this->assertSame($expected, $this->parse('<?php 3.14;'));
    }

    public function testBoolLiteral(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Literal(bool, true)
        TXT;
        $this->assertSame($expected, $this->parse('<?php true;'));
    }

    public function testNullLiteral(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Literal(NULL, NULL)
        TXT;
        $this->assertSame($expected, $this->parse('<?php null;'));
    }

    // =========================================================================
    //  Variables
    // =========================================================================

    public function testVariable(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Var($a)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a;'));
    }

    // =========================================================================
    //  Opérations binaires
    // =========================================================================

    public function testAddition(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(+)
              Literal(int, 1)
              Literal(int, 2)
        TXT;
        $this->assertSame($expected, $this->parse('<?php 1 + 2;'));
    }

    public function testPrecedenceMultiplicationOverAddition(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(+)
              Literal(int, 1)
              Binary(*)
                Literal(int, 2)
                Literal(int, 3)
        TXT;
        $this->assertSame($expected, $this->parse('<?php 1 + 2 * 3;'));
    }

    public function testParenthesesOverridePrecedence(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(*)
              Binary(+)
                Literal(int, 1)
                Literal(int, 2)
              Literal(int, 3)
        TXT;
        $this->assertSame($expected, $this->parse('<?php (1 + 2) * 3;'));
    }

    public function testLeftAssociativitySubtraction(): void
    {
        // 1 - 2 - 3 = (1 - 2) - 3
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(-)
              Binary(-)
                Literal(int, 1)
                Literal(int, 2)
              Literal(int, 3)
        TXT;
        $this->assertSame($expected, $this->parse('<?php 1 - 2 - 3;'));
    }

    public function testPowerRightAssociative(): void
    {
        // 2 ** 3 ** 2 = 2 ** (3 ** 2)
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(**)
              Literal(int, 2)
              Binary(**)
                Literal(int, 3)
                Literal(int, 2)
        TXT;
        $this->assertSame($expected, $this->parse('<?php 2 ** 3 ** 2;'));
    }

    // =========================================================================
    //  Unaires
    // =========================================================================

    public function testUnaryMinus(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Unary(-)
              Literal(int, 5)
        TXT;
        $this->assertSame($expected, $this->parse('<?php -5;'));
    }

    public function testUnaryNot(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Unary(!)
              Var($a)
        TXT;
        $this->assertSame($expected, $this->parse('<?php !$a;'));
    }

    public function testIncrementPrefix(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            PreIncDec(++)
              Var($a)
        TXT;
        $this->assertSame($expected, $this->parse('<?php ++$a;'));
    }

    public function testIncrementPostfix(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            PostIncDec(++)
              Var($a)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a++;'));
    }

    // =========================================================================
    //  Affectation
    // =========================================================================

    public function testSimpleAssign(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Assign(=)
              Var($a)
              Literal(int, 5)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a = 5;'));
    }

    public function testChainedAssign(): void
    {
        // $a = $b = 5 → $a = ($b = 5)
        $expected = <<<'TXT'
        Program
          ExprStmt
            Assign(=)
              Var($a)
              Assign(=)
                Var($b)
                Literal(int, 5)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a = $b = 5;'));
    }

    public function testCompoundAssign(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Assign(+=)
              Var($a)
              Literal(int, 1)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a += 1;'));
    }

    // =========================================================================
    //  Ternaire et coalesce
    // =========================================================================

    public function testTernary(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Ternary
              Cond
                Var($a)
              Then
                Literal(int, 1)
              Else
                Literal(int, 2)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a ? 1 : 2;'));
    }

    public function testShortTernary(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Ternary
              Cond
                Var($a)
              Else
                Literal(int, 2)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a ?: 2;'));
    }

    public function testCoalesce(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Coalesce
              Var($a)
              Literal(int, 5)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a ?? 5;'));
    }

    // =========================================================================
    //  Appels
    // =========================================================================

    public function testCallNoArgs(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Call
              Callee
                Var($f)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $f();'));
    }

    public function testCallWithArgs(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Call
              Callee
                Var($f)
              Args
                Literal(int, 1)
                Literal(int, 2)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $f(1, 2);'));
    }

    public function testIndex(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Index
              Target
                Var($a)
              Index
                Literal(int, 0)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a[0];'));
    }

    // =========================================================================
    //  Accès -> (définitif)
    // =========================================================================

    public function testArrowProperty(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            PropertyAccess(->x)
              Var($a)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a->x;'));
    }

    public function testArrowMethodCall(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            MethodCall(->foo)
              Var($a)
              Args
                Literal(int, 1)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a->foo(1);'));
    }

    // =========================================================================
    //  Accès . (ambigu, résolu en sémantique)
    // =========================================================================

    public function testDotConcatWithSpaces(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(.)
              Literal(string, 'a')
              Literal(string, 'b')
        TXT;
        $this->assertSame($expected, $this->parse('<?php "a" . "b";'));
    }

    public function testDotConcatSpaceBeforeOnly(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(.)
              Literal(string, 'a')
              Literal(string, 'b')
        TXT;
        $this->assertSame($expected, $this->parse('<?php "a" ."b";'));
    }

    public function testDotConcatSpaceAfterOnly(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Binary(.)
              Literal(string, 'a')
              Literal(string, 'b')
        TXT;
        $this->assertSame($expected, $this->parse('<?php "a". "b";'));
    }

    public function testDotAccessWithoutSpaces(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            DotAccess(.x)
              Var($a)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a.x;'));
    }

    public function testDotMethodCallWithoutSpaces(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            DotMethodCall(.foo)
              Var($a)
              Args
                Literal(int, 1)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $a.foo(1);'));
    }

    // =========================================================================
    //  New
    // =========================================================================

    public function testNew(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            New(Foo)
        TXT;
        $this->assertSame($expected, $this->parse('<?php new Foo;'));
    }

    public function testNewWithArgs(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            New(Foo)
              Args
                Literal(int, 1)
        TXT;
        $this->assertSame($expected, $this->parse('<?php new Foo(1);'));
    }

    public function testNewWithNamespace(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            New(Foo\\Bar)
        TXT;
        $this->assertSame($expected, $this->parse('<?php new Foo\\Bar;'));
    }

    // =========================================================================
    //  Statements de contrôle
    // =========================================================================

    public function testIfSimple(): void
    {
        $expected = <<<'TXT'
        Program
          If
            Cond
              Var($a)
            Then
              Block
                Echo
                  Literal(string, 'oui')
        TXT;
        $this->assertSame($expected, $this->parse('<?php if ($a) { echo "oui"; }'));
    }

    public function testIfElse(): void
    {
        $expected = <<<'TXT'
        Program
          If
            Cond
              Var($a)
            Then
              Block
            Else
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php if ($a) {} else {}'));
    }

    public function testIfElseif(): void
    {
        $expected = <<<'TXT'
        Program
          If
            Cond
              Var($a)
            Then
              Block
            ElseIf
              Cond
                Var($b)
              Body
                Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php if ($a) {} elseif ($b) {}'));
    }

    public function testWhile(): void
    {
        $expected = <<<'TXT'
        Program
          While
            Cond
              Var($a)
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php while ($a) {}'));
    }

    public function testFor(): void
    {
        $expected = <<<'TXT'
        Program
          For
            Init
              Assign(=)
                Var($i)
                Literal(int, 0)
            Cond
              Binary(<)
                Var($i)
                Literal(int, 10)
            Step
              PostIncDec(++)
                Var($i)
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php for ($i = 0; $i < 10; $i++) {}'));
    }

    public function testForeachSimple(): void
    {
        $expected = <<<'TXT'
        Program
          Foreach
            Iterable
              Var($arr)
            Value
              Var($v)
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php foreach ($arr as $v) {}'));
    }

    public function testForeachWithKey(): void
    {
        $expected = <<<'TXT'
        Program
          Foreach
            Iterable
              Var($arr)
            Key
              Var($k)
            Value
              Var($v)
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php foreach ($arr as $k => $v) {}'));
    }

    public function testReturn(): void
    {
        $expected = <<<'TXT'
        Program
          Return
            Literal(int, 5)
        TXT;
        $this->assertSame($expected, $this->parse('<?php return 5;'));
    }

    public function testReturnVoid(): void
    {
        $expected = <<<'TXT'
        Program
          Return
        TXT;
        $this->assertSame($expected, $this->parse('<?php return;'));
    }

    public function testBreak(): void
    {
        $expected = <<<'TXT'
        Program
          Break
        TXT;
        $this->assertSame($expected, $this->parse('<?php break;'));
    }

    public function testContinueWithLevel(): void
    {
        $expected = <<<'TXT'
        Program
          Continue 2
        TXT;
        $this->assertSame($expected, $this->parse('<?php continue 2;'));
    }

    // =========================================================================
    //  Erreurs
    // =========================================================================

    public function testMissingSemicolon(): void
    {
        $this->assertParseError('<?php $a = 5', "';' attendu");
    }

    public function testMissingClosingParen(): void
    {
        $this->assertParseError('<?php if ($a { }', "')' attendu");
    }

    public function testDotFiveRejected(): void
    {
        // .5 est tokenisé Dot + Int, et le parser rejette . en début d'expression
        $this->assertParseError('<?php .5;', 'Expression attendue');
    }

    public function testFunctionKeywordNotSupportedYet(): void
    {
        // En 2a on ne supporte pas encore les fonctions
        $this->assertParseError('<?php function f() {}', 'Expression attendue');
    }

    public function testMissingCloseTagIsFine(): void
    {
        // Pas de  à la fin, doit marcher
        $expected = <<<'TXT'
        Program
          Echo
            Literal(string, 'ok')
        TXT;
        $this->assertSame($expected, $this->parse('<?php echo "ok";'));
    }

    public function testCloseTagAccepted(): void
    {
        $expected = <<<'TXT'
        Program
          Echo
            Literal(string, 'ok')
        TXT;
        $this->assertSame($expected, $this->parse('<?php echo "ok"; ?>'));
    }
}