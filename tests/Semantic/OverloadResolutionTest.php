<?php

declare(strict_types=1);

namespace PPhp\Tests\Semantic;

use PPhp\Error\TypeError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\ClassHierarchy;
use PPhp\Semantic\Collector;
use PPhp\Semantic\TypeChecker;
use PHPUnit\Framework\TestCase;

final class OverloadResolutionTest extends TestCase
{
    private function check(string $source): void
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($source);
        $ast = $parser->parse($tokens);

        $collector = new Collector();
        $globals = $collector->collect($ast, '<test>');

        $hierarchy = new ClassHierarchy($globals);
        $hierarchy->validate('<test>');

        $checker = new TypeChecker($globals);
        $checker->check($ast, '<test>');
    }

    private function assertError(string $source, string $messagePart): void
    {
        try {
            $this->check($source);
            $this->fail('TypeError attendue');
        } catch (TypeError $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // =========================================================================
    //  Fonctions top-level
    // =========================================================================

    public function testResolveFunctionInt(): void
    {
        $this->check('<?php
            function f(int $x): void {}
            function f(string $x): void {}
            f(5);
        ');
        $this->assertTrue(true);
    }

    public function testResolveFunctionString(): void
    {
        $this->check('<?php
            function f(int $x): void {}
            function f(string $x): void {}
            f("hello");
        ');
        $this->assertTrue(true);
    }

    public function testResolveFunctionNoMatch(): void
    {
        $this->assertError('<?php
            function f(int $x): void {}
            function f(string $x): void {}
            f(true);
        ', 'Aucune surcharge');
    }

    public function testResolveFunctionWrongArgCount(): void
    {
        $this->assertError('<?php
            function f(int $x): void {}
            function f(string $x): void {}
            f(5, 6);
        ', 'attend 1 argument');
    }

    // =========================================================================
    //  Types de retour différents
    // =========================================================================

    public function testResolveFunctionReturnType(): void
    {
        $this->check('<?php
            function f(int $x): int { return $x; }
            function f(string $x): string { return $x; }
            int $a = f(5);
            string $b = f("hello");
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Méthodes
    // =========================================================================

    public function testResolveMethodInt(): void
    {
        $this->check('<?php
            class A {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
            A $a = new A();
            $a->f(5);
        ');
        $this->assertTrue(true);
    }

    public function testResolveMethodString(): void
    {
        $this->check('<?php
            class A {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
            A $a = new A();
            $a->f("hello");
        ');
        $this->assertTrue(true);
    }

    public function testResolveMethodNoMatch(): void
    {
        $this->assertError('<?php
            class A {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
            A $a = new A();
            $a->f(true);
        ', 'Aucune surcharge');
    }

    // =========================================================================
    //  Constructeur surchargé
    // =========================================================================

    public function testResolveConstructorInt(): void
    {
        $this->check('<?php
            class A {
                public function __construct(int $x) {}
                public function __construct(string $x) {}
            }
            new A(5);
        ');
        $this->assertTrue(true);
    }

    public function testResolveConstructorString(): void
    {
        $this->check('<?php
            class A {
                public function __construct(int $x) {}
                public function __construct(string $x) {}
            }
            new A("hello");
        ');
        $this->assertTrue(true);
    }

    public function testResolveConstructorNoMatch(): void
    {
        $this->assertError('<?php
            class A {
                public function __construct(int $x) {}
                public function __construct(string $x) {}
            }
            new A(true);
        ', 'Aucune surcharge');
    }

    // =========================================================================
    //  Fusion hiérarchique
    // =========================================================================

    public function testInheritOverloads(): void
{
    // Child hérite de f(string) et redéfinit f(int)
    $this->check('<?php
        class Base {
            public function f(int $x): void {}
            public function f(string $x): void {}
        }
        class Child extends Base {
            public function f(int $x): void {}
        }
        Child $c = new Child();
        $c->f(5);
        $c->f("hello");
    ');
    $this->assertTrue(true);
}

    // =========================================================================
    //  Sous-type en argument
    // =========================================================================

    public function testSubtypeArgMatches(): void
    {
        $this->check('<?php
            class Animal {}
            class Dog extends Animal {}
            function f(Animal $a): void {}
            function f(string $s): void {}
            Dog $d = new Dog();
            f($d);
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  mixed ne matche rien
    // =========================================================================

    public function testMixedArgNoMatch(): void
    {
        $this->assertError('<?php
            function f(int $x): void {}
            function f(string $x): void {}
            mixed $m = 5;
            f($m);
        ', 'Aucune surcharge');
    }
}