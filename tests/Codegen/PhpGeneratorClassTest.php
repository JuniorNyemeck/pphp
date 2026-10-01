<?php

declare(strict_types=1);

namespace PPhp\Tests\Codegen;

use PPhp\Codegen\PhpGenerator;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class PhpGeneratorClassTest extends TestCase
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
    //  Fonctions
    // =========================================================================

    public function testEmptyFunction(): void
    {
        $php = $this->generate('<?pphp function f(): void {}');
        $this->assertStringContainsString('function f(): void {', $php);
    }

    public function testFunctionWithParam(): void
    {
        $php = $this->generate('<?pphp function f(int $x): int { return $x; }');
        $this->assertStringContainsString('function f(int $x): int {', $php);
        $this->assertStringContainsString('return $x;', $php);
    }

    public function testFunctionWithDefault(): void
    {
        $php = $this->generate('<?pphp function f(int $x = 5): void {}');
        $this->assertStringContainsString('function f(int $x = 5): void', $php);
    }

    public function testFunctionWithArrayType(): void
    {
        $php = $this->generate('<?pphp function f(int[] $arr): void {}');
        // int[] → array
        $this->assertStringContainsString('function f(array $arr): void', $php);
    }

    public function testFunctionWithNullableReturn(): void
    {
        $php = $this->generate('<?pphp function f(): ?int { return null; }');
        $this->assertStringContainsString('function f(): ?int', $php);
    }

    // =========================================================================
    //  Classes
    // =========================================================================

    public function testEmptyClass(): void
    {
        $php = $this->generate('<?pphp class Foo {}');
        $this->assertStringContainsString('class Foo {', $php);
    }

    public function testClassExtends(): void
    {
        $php = $this->generate('<?pphp class Bar {} class Foo extends Bar {}');
        $this->assertStringContainsString('class Foo extends Bar {', $php);
    }

    public function testClassImplements(): void
    {
        $php = $this->generate('<?pphp interface I {} class Foo implements I {}');
        $this->assertStringContainsString('class Foo implements I {', $php);
    }

    public function testClassWithProperty(): void
    {
        $php = $this->generate('<?pphp class Foo { public int $x; }');
        $this->assertStringContainsString('public int $x;', $php);
    }

    public function testClassWithPropertyAndInit(): void
    {
        $php = $this->generate('<?pphp class Foo { public int $x = 5; }');
        $this->assertStringContainsString('public int $x = 5;', $php);
    }

    public function testClassWithMethod(): void
    {
        $php = $this->generate('<?pphp class Foo { public function bar(): int { return 1; } }');
        $this->assertStringContainsString('public function bar(): int', $php);
        $this->assertStringContainsString('return 1;', $php);
    }

    public function testClassWithAbstractMethod(): void
    {
        $php = $this->generate('<?pphp abstract class Foo { abstract public function bar(): int; }');
        $this->assertStringContainsString('abstract class Foo', $php);
        $this->assertStringContainsString('abstract public function bar(): int;', $php);
    }

    // =========================================================================
    //  Interfaces
    // =========================================================================

    public function testEmptyInterface(): void
    {
        $php = $this->generate('<?pphp interface I {}');
        $this->assertStringContainsString('interface I {', $php);
    }

    public function testInterfaceWithMethod(): void
    {
        $php = $this->generate('<?pphp interface I { public function f(): int; }');
        $this->assertStringContainsString('interface I {', $php);
        $this->assertStringContainsString('public function f(): int;', $php);
    }

    // =========================================================================
    //  Modificateurs
    // =========================================================================

    public function testFinalClass(): void
    {
        $php = $this->generate('<?pphp final class Foo {}');
        $this->assertStringContainsString('final class Foo', $php);
    }

    public function testStaticMethod(): void
    {
        $php = $this->generate('<?pphp class Foo { public static function bar(): void {} }');
        $this->assertStringContainsString('public static function bar(): void', $php);
    }

    public function testPrivateProperty(): void
    {
        $php = $this->generate('<?pphp class Foo { private string $name; }');
        $this->assertStringContainsString('private string $name;', $php);
    }

    public function testProtectedMethod(): void
    {
        $php = $this->generate('<?pphp class Foo { protected function bar(): void {} }');
        $this->assertStringContainsString('protected function bar(): void', $php);
    }

    // =========================================================================
    //  Types non natifs
    // =========================================================================

    public function testArrayTypeInProperty(): void
    {
        $php = $this->generate('<?pphp class Foo { public string[] $names; }');
        // string[] → array
        $this->assertStringContainsString('public array $names;', $php);
    }

    public function testMixedType(): void
    {
        $php = $this->generate('<?pphp function f(mixed $x): void {}');
        $this->assertStringContainsString('function f(mixed $x): void', $php);
    }

    public function testUnionType(): void
    {
        $php = $this->generate('<?pphp function f(int|string $x): void {}');
        $this->assertStringContainsString('function f(int|string $x): void', $php);
    }
}