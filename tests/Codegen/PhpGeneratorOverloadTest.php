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

final class PhpGeneratorOverloadTest extends TestCase
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
        return $generator->generate($ast, $registry);
    }

    public function testOverloadedFunctionNames(): void
    {
        $php = $this->generate('<?pphp
            function f(int $x): void {}
            function f(string $x): void {}
        ');
        $this->assertStringContainsString('function f__int(int $x)', $php);
        $this->assertStringContainsString('function f__string(string $x)', $php);
    }

    public function testNonOverloadedFunctionName(): void
    {
        $php = $this->generate('<?pphp function f(int $x): void {}');
        $this->assertStringContainsString('function f(int $x)', $php);
        $this->assertStringNotContainsString('f__int', $php);
    }

    public function testOverloadedMethodNames(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
        ');
        $this->assertStringContainsString('function f__int(int $x)', $php);
        $this->assertStringContainsString('function f__string(string $x)', $php);
    }

    public function testNonOverloadedMethodName(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function f(int $x): void {}
            }
        ');
        $this->assertStringContainsString('function f(int $x)', $php);
        $this->assertStringNotContainsString('f__int', $php);
    }

    public function testOverloadedConstructorDispatcher(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function __construct(int $x) {}
                public function __construct(string $x) {}
            }
        ');
        // Dispatcher
        $this->assertStringContainsString('function __construct(...$args)', $php);
        // Surcharges
        $this->assertStringContainsString('__construct__int', $php);
        $this->assertStringContainsString('__construct__string', $php);
    }
}