<?php

declare(strict_types=1);

namespace PPhp\Tests\Semantic;

use PPhp\Error\TypeError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\ClassHierarchy;
use PPhp\Semantic\Collector;
use PPhp\Semantic\GlobalScope;
use PPhp\Semantic\SubtypeChecker;
use PPhp\Semantic\TypeChecker;
use PHPUnit\Framework\TestCase;

final class AbstractFinalTest extends TestCase
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
    //  final class
    // =========================================================================

    public function testFinalClassCannotBeExtended(): void
    {
        $this->assertError(
            '<?php final class A {} class B extends A {}',
            "Impossible d'étendre la classe finale"
        );
    }

    public function testFinalClassAloneOk(): void
    {
        $this->check('<?php final class A {}');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  abstract class
    // =========================================================================

    public function testAbstractClassCannotBeInstantiated(): void
{
    $this->assertError(
        '<?php abstract class A {} new A();',
        "Impossible d'instancier la classe abstraite"
    );
}

    public function testAbstractClassOkWithoutNew(): void
    {
        $this->check('<?php abstract class A {}');
        $this->assertTrue(true);
    }

    public function testAbstractAndFinalOnClassForbidden(): void
    {
        $this->assertError(
            '<?php abstract final class A {}',
            "ne peut pas être à la fois 'abstract' et 'final'"
        );
    }

    // =========================================================================
    //  abstract method
    // =========================================================================

    public function testAbstractMethodInAbstractClassOk(): void
    {
        $this->check('<?php abstract class A { abstract public function f(): void; }');
        $this->assertTrue(true);
    }

    public function testAbstractMethodInConcreteClassForbidden(): void
    {
        $this->assertError(
            '<?php class A { abstract public function f(): void; }',
            "doit être dans une classe abstract ou une interface"
        );
    }

    public function testAbstractAndFinalOnMethodForbidden(): void
    {
        $this->assertError(
            '<?php abstract class A { abstract final public function f(): void; }',
            "ne peut pas être à la fois 'abstract' et 'final'"
        );
    }

    // =========================================================================
    //  Implémentation des méthodes abstraites
    // =========================================================================

    public function testConcreteClassMustImplementAbstractMethod(): void
    {
        $this->assertError(
            '<?php
                abstract class A { abstract public function f(): void; }
                class B extends A {}
            ',
            "doit implémenter les méthodes abstraites"
        );
    }

    public function testConcreteClassImplementingAbstractMethodOk(): void
    {
        $this->check('<?php
            abstract class A { abstract public function f(): void; }
            class B extends A {
                public function f(): void {}
            }
        ');
        $this->assertTrue(true);
    }

    public function testAbstractClassCanLeaveAbstractMethodsUnimplemented(): void
    {
        $this->check('<?php
            abstract class A { abstract public function f(): void; }
            abstract class B extends A {}
        ');
        $this->assertTrue(true);
    }

    public function testTransitiveAbstractImplementation(): void
    {
        $this->check('<?php
            abstract class A { abstract public function f(): void; }
            abstract class B extends A {}
            class C extends B {
                public function f(): void {}
            }
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Interfaces
    // =========================================================================

    public function testInterfaceMethodImplementedByClass(): void
    {
        $this->check('<?php
            interface I { public function f(): void; }
            class A implements I {
                public function f(): void {}
            }
        ');
        $this->assertTrue(true);
    }

    public function testClassNotImplementingInterfaceMethodIsError(): void
    {
        $this->assertError(
            '<?php
                interface I { public function f(): void; }
                class A implements I {}
            ',
            "doit implémenter les méthodes d'interface"
        );
    }

    public function testInterfaceCannotHaveProperties(): void
    {
        $this->assertError(
            '<?php interface I { public int $x; }',
            "Une interface ne peut pas déclarer de propriétés"
        );
    }

    public function testInterfaceInheritance(): void
    {
        $this->check('<?php
            interface I { public function f(): void; }
            interface J extends I {}
            abstract class A implements J {}
        ');
        $this->assertTrue(true);
    }

    public function testInterfaceInheritanceImplementation(): void
    {
        $this->check('<?php
            interface I { public function f(): void; }
            interface J extends I { public function g(): void; }
            class A implements J {
                public function f(): void {}
                public function g(): void {}
            }
        ');
        $this->assertTrue(true);
    }

    public function testTransitiveInterfaceImplementation(): void
    {
        $this->check('<?php
            interface I { public function f(): void; }
            class A implements I {
                public function f(): void {}
            }
            class B extends A {}
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Mixte
    // =========================================================================

    public function testAbstractClassImplementsInterfaceWithoutImplementing(): void
    {
        $this->check('<?php
            interface I { public function f(): void; }
            abstract class A implements I {}
        ');
        $this->assertTrue(true);
    }

    public function testMultipleInterfaces(): void
    {
        $this->check('<?php
            interface I { public function f(): void; }
            interface J { public function g(): void; }
            class A implements I, J {
                public function f(): void {}
                public function g(): void {}
            }
        ');
        $this->assertTrue(true);
    }

    public function testMultipleInterfacesOneMissing(): void
    {
        $this->assertError(
            '<?php
                interface I { public function f(): void; }
                interface J { public function g(): void; }
                class A implements I, J {
                    public function f(): void {}
                }
            ',
            "doit implémenter les méthodes d'interface"
        );
    }
}