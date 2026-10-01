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

final class OverloadOverrideTest extends TestCase
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
    //  Override partiel
    // =========================================================================

    public function testPartialOverride(): void
    {
        // Child redéfinit f(int) mais pas f(string)
        $this->check('<?pphp
            class Base {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
            class Child extends Base {
                public function f(int $x): void {}
            }
        ');
        $this->assertTrue(true);
    }

    public function testFullOverride(): void
    {
        $this->check('<?pphp
            class Base {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
            class Child extends Base {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
        ');
        $this->assertTrue(true);
    }

    public function testNewOverloadNotOverride(): void
    {
        // Child ajoute f(bool) qui n'existe pas dans Base
        $this->check('<?pphp
            class Base {
                public function f(int $x): void {}
            }
            class Child extends Base {
                public function f(int $x): void {}
                public function f(bool $x): void {}
            }
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  final sur une surcharge
    // =========================================================================

    public function testFinalOnSpecificOverload(): void
    {
        $this->assertError('<?pphp
            class Base {
                final public function f(int $x): void {}
                public function f(string $x): void {}
            }
            class Child extends Base {
                public function f(int $x): void {}
            }
        ', "Impossible d'overrider la méthode finale");
    }

    public function testFinalOnOneOverloadOtherAllowed(): void
    {
        // f(int) est final, f(string) ne l'est pas
        $this->check('<?pphp
            class Base {
                final public function f(int $x): void {}
                public function f(string $x): void {}
            }
            class Child extends Base {
                public function f(string $x): void {}
            }
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Covariance par surcharge
    // =========================================================================

    public function testCovariancePerOverload(): void
    {
        $this->check('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class Base {
                public function get(int $x): Animal { return new Animal(); }
                public function get(string $x): Animal { return new Animal(); }
            }
            class Child extends Base {
                public function get(int $x): Dog { return new Dog(); }
                // get(string) reste hérité
            }
        ');
        $this->assertTrue(true);
    }

    public function testCovarianceViolationPerOverload(): void
    {
        $this->assertError('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class Base {
                public function get(int $x): Dog { return new Dog(); }
                public function get(string $x): Animal { return new Animal(); }
            }
            class Child extends Base {
                public function get(int $x): Animal { return new Animal(); }
            }
        ', 'covariance violée');
    }

    // =========================================================================
    //  Contravariance par surcharge
    // =========================================================================

    /* public function testContravariancePerOverload(): void
    {
        $this->check('<?pphp
            class Animal {}
            class Dog extends Animal {}
            class Base {
                public function f(Dog $d): void {}
                public function f(int $x): void {}
            }
            class Child extends Base {
                public function f(Animal $a): void {}
                // f(int) reste hérité
            }
        ');
        $this->assertTrue(true);
    }
 */
    public function testContravarianceViolationPerOverload(): void
{
    $this->assertError('<?pphp
        class Animal {}
        class Dog extends Animal {}
        class Chihuahua extends Dog {}
        class Base {
            public function f(Dog $d): void {}
            public function f(int $x): void {}
        }
        class Child extends Base {
            public function f(Chihuahua $c): void {}
        }
    ', 'contravariance violée');
}

    // =========================================================================
    //  Interface + surcharge
    // =========================================================================

    public function testInterfaceMethodPlusOverload(): void
    {
        $this->check('<?pphp
            interface I {
                public function f(int $x): void;
            }
            class C implements I {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
        ');
        $this->assertTrue(true);
    }

    public function testInterfaceMethodCovarianceViolation(): void
{
    $this->assertError('<?pphp
        class Animal {}
        class Dog extends Animal {}
        interface I {
            public function get(int $x): Dog;
        }
        class C implements I {
            public function get(int $x): Animal { return new Animal(); }
            public function get(string $x): Dog { return new Dog(); }
        }
    ', 'covariance violée');
}


public function testContravariancePerOverload(): void
{
    $this->check('<?pphp
        class Animal {}
        class Dog extends Animal {}
        class Base {
            public function f(Dog $d): void {}
            public function f(int $x): void {}
        }
        class Child extends Base {
            public function f(Animal $a): void {}
            // f(int) reste héritée
        }
    ');
    $this->assertTrue(true);
}
}