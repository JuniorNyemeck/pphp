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

final class OverrideTest extends TestCase
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
    //  Override valide
    // =========================================================================

    public function testSimpleOverride(): void
    {
        $this->check('<?pphp
            class A { public function f(): void {} }
            class B extends A { public function f(): void {} }
        ');
        $this->assertTrue(true);
    }

    public function testOverrideRenamedParamOk(): void
    {
        $this->check('<?pphp
            class A { public function f(int $x): void {} }
            class B extends A { public function f(int $y): void {} }
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Covariance du retour
    // =========================================================================

    public function testCovariantReturnOk(): void
    {
        $this->check('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class A { public function get(): Animal { return new Animal(); } }
            class B extends A { public function get(): Dog { return new Dog(); } }
        ');
        $this->assertTrue(true);
    }

    public function testCovariantReturnViolation(): void
    {
        $this->assertError('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class A { public function get(): Dog { return new Dog(); } }
            class B extends A { public function get(): Animal { return new Animal(); } }
        ', 'covariance violée');
    }

    // =========================================================================
    //  Contravariance des paramètres
    // =========================================================================

    public function testContravariantParamOk(): void
    {
        $this->check('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class A { public function f(Dog $d): void {} }
            class B extends A { public function f(Animal $a): void {} }
        ');
        $this->assertTrue(true);
    }

 /*    public function testContravariantParamViolation(): void
{
    $this->assertError('<?pphp
        class Animal {}
        class Dog extends Animal {}
        class Chihuahua extends Dog {}
        class A { public function f(Dog $d): void {} }
        class B extends A { public function f(Chihuahua $c): void {} }
    ', 'ne sont pas disjointes');
} */

    // =========================================================================
    //  Nombre de paramètres
    // =========================================================================

    public function testParamCountMismatch(): void
    {
        $this->assertError('<?pphp
            class A { public function f(int $x): void {} }
            class B extends A { public function f(int $x, int $y): void {} }
        ', 'même nombre de paramètres');
    }

    // =========================================================================
    //  Visibilité
    // =========================================================================

    public function testPublicToProtectedForbidden(): void
    {
        $this->assertError('<?pphp
            class A { public function f(): void {} }
            class B extends A { protected function f(): void {} }
        ', 'Visibilité réduite');
    }

    public function testProtectedToPublicAllowed(): void
    {
        $this->check('<?pphp
            class A { protected function f(): void {} }
            class B extends A { public function f(): void {} }
        ');
        $this->assertTrue(true);
    }

    public function testProtectedToPrivateForbidden(): void
    {
        $this->assertError('<?pphp
            class A { protected function f(): void {} }
            class B extends A { private function f(): void {} }
        ', 'Visibilité réduite');
    }

    // =========================================================================
    //  final
    // =========================================================================

    public function testOverrideFinalMethodForbidden(): void
    {
        $this->assertError('<?pphp
            class A { final public function f(): void {} }
            class B extends A { public function f(): void {} }
        ', "Impossible d'overrider la méthode finale");
    }

    // =========================================================================
    //  static
    // =========================================================================

    public function testStaticMismatchForbidden(): void
    {
        $this->assertError('<?pphp
            class A { public static function f(): void {} }
            class B extends A { public function f(): void {} }
        ', 'doit être static');
    }

    public function testNonStaticMismatchForbidden(): void
    {
        $this->assertError('<?pphp
            class A { public function f(): void {} }
            class B extends A { public static function f(): void {} }
        ', 'doit être non-static');
    }

    // =========================================================================
    //  abstract en override
    // =========================================================================

    public function testConcreteToAbstractForbidden(): void
    {
        $this->assertError('<?pphp
            class A { public function f(): void {} }
            abstract class B extends A { abstract public function f(): void; }
        ', 'ne peut pas devenir abstract');
    }

    public function testAbstractToConcreteOk(): void
    {
        $this->check('<?pphp
            abstract class A { abstract public function f(): void; }
            class B extends A { public function f(): void {} }
        ');
        $this->assertTrue(true);
    }

    public function testAbstractToAbstractOk(): void
    {
        $this->check('<?pphp
            abstract class A { abstract public function f(): void; }
            abstract class B extends A { abstract public function f(): void; }
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  private
    // =========================================================================

    public function testPrivateParentNotOverride(): void
    {
        $this->check('<?pphp
            class A { private function f(): void {} }
            class B extends A { private function f(): void {} }
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Propriétés
    // =========================================================================

    public function testPropertyCovarianceOk(): void
    {
        $this->check('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class A { public Animal $pet; }
            class B extends A { public Dog $pet; }
        ');
        $this->assertTrue(true);
    }

    public function testPropertyCovarianceViolation(): void
    {
        $this->assertError('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class A { public Dog $pet; }
            class B extends A { public Animal $pet; }
        ', 'covariance violée');
    }

    public function testPropertyVisibilityReductionForbidden(): void
    {
        $this->assertError('<?pphp
            class A { public int $x; }
            class B extends A { protected int $x; }
        ', 'Visibilité réduite');
    }

    public function testPrivatePropertyNotOverride(): void
    {
        $this->check('<?pphp
            class A { private int $x; }
            class B extends A { private int $x; }
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Interfaces
    // =========================================================================

    public function testInterfaceImplementationRespectsCovariance(): void
    {
        $this->check('<?pphp
            class Animal {}
            class Dog extends Animal {}
            interface I { public function get(): Animal; }
            class A implements I { public function get(): Dog { return new Dog(); } }
        ');
        $this->assertTrue(true);
    }

    public function testInterfaceImplementationCovarianceViolation(): void
    {
        $this->assertError('<?pphp
            class Animal {}
            class Dog extends Animal {}
            interface I { public function get(): Dog; }
            class A implements I { public function get(): Animal { return new Animal(); } }
        ', 'covariance violée');
    }

    public function testContravariantParamViolation(): void
{
    $this->assertError('<?pphp
        class Animal {}
        class Dog extends Animal {}
        class Chihuahua extends Dog {}
        class A { public function f(Dog $d): void {} }
        class B extends A { public function f(Chihuahua $c): void {} }
    ', 'contravariance violée');
}
}