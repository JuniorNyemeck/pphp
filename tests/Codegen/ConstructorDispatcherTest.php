<?php

declare(strict_types=1);

namespace PPhp\Tests\Codegen;

use PPhp\Codegen\MethodRegistry;
use PPhp\Codegen\PhpGenerator;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\ClassHierarchy;
use PPhp\Semantic\Collector;
use PPhp\Semantic\TypeChecker;
use PHPUnit\Framework\TestCase;

final class ConstructorDispatcherTest extends TestCase
{
    private function generate(string $source): string
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($source, '<test>');
        $ast = $parser->parse($tokens, '<test>');

        $collector = new Collector();
        $globals = $collector->collect($ast, '<test>');

        $hierarchy = new ClassHierarchy($globals);
        $hierarchy->validate('<test>');

        $checker = new TypeChecker($globals);
        $checker->check($ast, '<test>');

        $registry = new MethodRegistry($globals);
        $generator = new PhpGenerator();
        return $generator->generate($ast, $registry, []);
    }

    public function testIntAndString(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function __construct(int $x) {}
                public function __construct(string $x) {}
            }
        ');
        $this->assertStringContainsString('function __construct(...$args)', $php);
        $this->assertStringContainsString('is_int($args[0])', $php);
        $this->assertStringContainsString('is_string($args[0])', $php);
        $this->assertStringContainsString('return $this->__construct__int($args[0]);', $php);
        $this->assertStringContainsString('return $this->__construct__string($args[0]);', $php);
    }

    public function testTwoParams(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function __construct(int $x, string $y) {}
                public function __construct(string $x, int $y) {}
            }
        ');
        $this->assertStringContainsString('is_int($args[0]) && is_string($args[1])', $php);
        $this->assertStringContainsString('is_string($args[0]) && is_int($args[1])', $php);
        $this->assertStringContainsString('return $this->__construct__int_string($args[0], $args[1]);', $php);
        $this->assertStringContainsString('return $this->__construct__string_int($args[0], $args[1]);', $php);
    }

    public function testNullableInt(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function __construct(?int $x) {}
                public function __construct(string $x) {}
            }
        ');
        $this->assertStringContainsString('(is_int($args[0]) || $args[0] === null)', $php);
        $this->assertStringContainsString('is_string($args[0])', $php);
    }

    public function testClassParam(): void
    {
        $php = $this->generate('<?pphp
            class Foo {}
            class Bar {}
            class A {
                public function __construct(Foo $x) {}
                public function __construct(Bar $x) {}
            }
        ');
        $this->assertStringContainsString('$args[0] instanceof Foo', $php);
        $this->assertStringContainsString('$args[0] instanceof Bar', $php);
    }

    public function testFloatAcceptsInt(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function __construct(float $x) {}
                public function __construct(string $x) {}
            }
        ');
        $this->assertStringContainsString('(is_float($args[0]) || is_int($args[0]))', $php);
    }

    public function testSingleConstructorNoDispatcher(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function __construct(int $x) {}
            }
        ');
        $this->assertStringNotContainsString('function __construct(...$args)', $php);
        $this->assertStringNotContainsString('__construct__int', $php);
        $this->assertStringContainsString('public function __construct(int $x)', $php);
    }
}