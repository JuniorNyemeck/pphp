<?php

declare(strict_types=1);

namespace PPhp\Tests\Parser;

use PPhp\Error\ParserError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\AstPrinter;
use PPhp\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class Parser2bTest extends TestCase
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
    //  Déclarations typées
    // =========================================================================

    public function testSimpleTypedVarDecl(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(int)
            Declarator($a)
        TXT;
        $this->assertSame($expected, $this->parse('<?php int $a;'));
    }

    public function testTypedVarDeclWithInit(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(int)
            Declarator($a)
              Literal(int, 5)
        TXT;
        $this->assertSame($expected, $this->parse('<?php int $a = 5;'));
    }

    public function testTypedVarDeclMultiple(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(int)
            Declarator($a)
            Declarator($b)
              Literal(int, 2)
            Declarator($c)
        TXT;
        $this->assertSame($expected, $this->parse('<?php int $a, $b = 2, $c;'));
    }

    public function testArrayType(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(string[])
            Declarator($keys)
        TXT;
        $this->assertSame($expected, $this->parse('<?php string[] $keys;'));
    }

    public function testNestedArrayType(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(int[][])
            Declarator($m)
        TXT;
        $this->assertSame($expected, $this->parse('<?php int[][] $m;'));
    }

    public function testNullableType(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(?string)
            Declarator($s)
        TXT;
        $this->assertSame($expected, $this->parse('<?php ?string $s;'));
    }

    public function testUnionType(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(int|string)
            Declarator($x)
        TXT;
        $this->assertSame($expected, $this->parse('<?php int|string $x;'));
    }

    public function testClassType(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(Foo)
            Declarator($f)
        TXT;
        $this->assertSame($expected, $this->parse('<?php Foo $f;'));
    }

    public function testQualifiedClassType(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(\Foo\Bar)
            Declarator($f)
        TXT;
        $this->assertSame($expected, $this->parse('<?php \\Foo\\Bar $f;'));
    }

    public function testDeclarationWithExpression(): void
    {
        $expected = <<<'TXT'
        Program
          VarDecl(int)
            Declarator($a)
              Binary(+)
                Literal(int, 1)
                Literal(int, 2)
        TXT;
        $this->assertSame($expected, $this->parse('<?php int $a = 1 + 2;'));
    }

    // =========================================================================
    //  Fonctions
    // =========================================================================

    public function testFunctionNoParams(): void
    {
        $expected = <<<'TXT'
        Program
          FunctionDecl(f: void)
            Params
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php function f(): void {}'));
    }

    public function testFunctionWithParams(): void
    {
        $expected = <<<'TXT'
        Program
          FunctionDecl(f: int)
            Params
              Param(int $x)
              Param(string $y)
            Body
              Block
                Return
                  Var($x)
        TXT;
        $this->assertSame($expected, $this->parse('<?php function f(int $x, string $y): int { return $x; }'));
    }

    public function testFunctionWithDefault(): void
    {
        $expected = <<<'TXT'
        Program
          FunctionDecl(f: void)
            Params
              Param(int $x = ...)
                Literal(int, 5)
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php function f(int $x = 5): void {}'));
    }

    public function testFunctionWithVariadic(): void
    {
        $expected = <<<'TXT'
        Program
          FunctionDecl(f: void)
            Params
              Param(int ...$nums)
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php function f(int ...$nums): void {}'));
    }

    public function testFunctionWithByRef(): void
    {
        $expected = <<<'TXT'
        Program
          FunctionDecl(f: void)
            Params
              Param(int &$x)
            Body
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php function f(int &$x): void {}'));
    }

    public function testFunctionMissingParamType(): void
    {
        $this->assertParseError('<?php function f($x): void {}', 'Type de paramètre obligatoire');
    }

    public function testFunctionMissingReturnType(): void
    {
        $this->assertParseError('<?php function f(int $x) {}', "':' attendu avant le type de retour");
    }

    // =========================================================================
    //  Classes
    // =========================================================================

    public function testEmptyClass(): void
    {
        $expected = <<<'TXT'
        Program
          ClassDecl(Foo)
        TXT;
        $this->assertSame($expected, $this->parse('<?php class Foo {}'));
    }

    public function testClassExtends(): void
    {
        $expected = <<<'TXT'
        Program
          ClassDecl(Foo extends Bar)
        TXT;
        $this->assertSame($expected, $this->parse('<?php class Foo extends Bar {}'));
    }

    public function testClassImplements(): void
    {
        $expected = <<<'TXT'
        Program
          ClassDecl(Foo implements I1, I2)
        TXT;
        $this->assertSame($expected, $this->parse('<?php class Foo implements I1, I2 {}'));
    }

    public function testInterface(): void
    {
        $expected = <<<'TXT'
        Program
          InterfaceDecl(I)
        TXT;
        $this->assertSame($expected, $this->parse('<?php interface I {}'));
    }

    public function testClassWithProperty(): void
    {
        $expected = <<<'TXT'
        Program
          ClassDecl(Foo)
            PropertyDecl(public int)
              Declarator($x)
        TXT;
        $this->assertSame($expected, $this->parse('<?php class Foo { public int $x; }'));
    }

    public function testClassWithMethod(): void
    {
        $expected = <<<'TXT'
        Program
          ClassDecl(Foo)
            MethodDecl(public getX: int)
              Params
              Body
                Block
                  Return
                    PropertyAccess(->x)
                      This
        TXT;
        $this->assertSame($expected, $this->parse('<?php class Foo { public function getX(): int { return $this->x; } }'));
    }

    public function testClassWithAbstractMethod(): void
    {
        $expected = <<<'TXT'
        Program
          ClassDecl(Foo)
            MethodDecl(public abstract m: void)
              Params
              Body
                <abstract>
        TXT;
        $this->assertSame($expected, $this->parse('<?php class Foo { public abstract function m(): void; }'));
    }

    public function testInterfaceWithMethodSignature(): void
    {
        $expected = <<<'TXT'
        Program
          InterfaceDecl(I)
            MethodDecl(public m: int)
              Params
                Param(int $x)
              Body
                <abstract>
        TXT;
        $this->assertSame($expected, $this->parse('<?php interface I { public function m(int $x): int; }'));
    }

    // =========================================================================
    //  $this
    // =========================================================================

    public function testThisInMethod(): void
    {
        $expected = <<<'TXT'
        Program
          ClassDecl(Foo)
            MethodDecl(public f: void)
              Params
              Body
                Block
                  ExprStmt
                    Assign(=)
                      PropertyAccess(->x)
                        This
                      Literal(int, 1)
        TXT;
        $this->assertSame($expected, $this->parse('<?php class Foo { public function f(): void { $this->x = 1; } }'));
    }

    public function testThisReservedAsVariable(): void
    {
        $this->assertParseError('<?php int $this;', "'\$this' est réservé");
    }

    public function testThisReservedAsParam(): void
    {
        $this->assertParseError('<?php function f(int $this): void {}', "'\$this' est réservé");
    }

    // =========================================================================
    //  instanceof
    // =========================================================================

    public function testInstanceof(): void
    {
        $expected = <<<'TXT'
        Program
          ExprStmt
            Instanceof(Foo)
              Var($x)
        TXT;
        $this->assertSame($expected, $this->parse('<?php $x instanceof Foo;'));
    }

    public function testInstanceofInCondition(): void
    {
        $expected = <<<'TXT'
        Program
          If
            Cond
              Instanceof(Foo)
                Var($x)
            Then
              Block
        TXT;
        $this->assertSame($expected, $this->parse('<?php if ($x instanceof Foo) {}'));
    }
}