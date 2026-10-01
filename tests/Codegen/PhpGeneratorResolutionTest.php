<?php

declare(strict_types=1);

namespace PPhp\Tests\Codegen;

use PPhp\Codegen\MethodRegistry;
use PPhp\Codegen\PhpGenerator;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\ClassHierarchy;
use PPhp\Semantic\Collector;
use PPhp\Semantic\FunctionInfo;
use PPhp\Semantic\MethodInfo;
use PPhp\Semantic\TypeChecker;
use PHPUnit\Framework\TestCase;

final class PhpGeneratorResolutionTest extends TestCase
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
        $resolvedNames = [];
        foreach ($checker->resolvedCalls() as $key => $info) {
            if ($info instanceof MethodInfo) {
                $overloads = $globals->getClass($info->declaringClass)?->methods[$info->name] ?? [];
                $index = array_search($info, $overloads, true);
                if ($index !== false) {
                    $resolvedNames[$key] = $registry->methodName(
                        $info->declaringClass,
                        $info->name,
                        $index,
                    );
                }
            } elseif ($info instanceof FunctionInfo) {
                $overloads = $globals->getFunctions($info->name);
                $index = array_search($info, $overloads, true);
                if ($index !== false) {
                    $resolvedNames[$key] = $registry->functionName($info->name, $index);
                }
            }
        }

        $generator = new PhpGenerator();
        return $generator->generate($ast, $registry, $resolvedNames);
    }

    // =========================================================================
    //  Fonctions surchargées
    // =========================================================================

    public function testResolveFunctionCall(): void
    {
        $php = $this->generate('<?pphp
            function f(int $x): void {}
            function f(string $x): void {}
            f(5);
            f("hello");
        ');
        $this->assertStringContainsString('f__int(5);', $php);
        $this->assertStringContainsString("f__string('hello');", $php);
    }

    public function testNonOverloadedFunctionCall(): void
    {
        $php = $this->generate('<?pphp
            function f(int $x): void {}
            f(5);
        ');
        // Pas manglé : appel direct à f
        $this->assertStringContainsString('f(5);', $php);
        $this->assertStringNotContainsString('f__int(5);', $php);
    }

    // =========================================================================
    //  Méthodes surchargées
    // =========================================================================

    public function testResolveMethodCall(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
            A $a = new A();
            $a->f(5);
            $a->f("hello");
        ');
        $this->assertStringContainsString('$a->f__int(5);', $php);
        $this->assertStringContainsString("\$a->f__string('hello');", $php);
    }

    public function testNonOverloadedMethodCall(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function f(int $x): void {}
            }
            A $a = new A();
            $a->f(5);
        ');
        $this->assertStringContainsString('$a->f(5);', $php);
        $this->assertStringNotContainsString('$a->f__int(5);', $php);
    }

    public function testResolveMethodCallWithDot(): void
    {
        $php = $this->generate('<?pphp
            class A {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
            A $a = new A();
            $a.f(5);
            $a.f("hello");
        ');
        $this->assertStringContainsString('$a->f__int(5);', $php);
        $this->assertStringContainsString("\$a->f__string('hello');", $php);
    }
}