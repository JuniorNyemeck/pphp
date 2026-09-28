<?php

declare(strict_types=1);

namespace PPhp\Tests\Semantic;

use PPhp\Error\TypeError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\Collector;
use PPhp\Semantic\SubtypeChecker;
use PPhp\Semantic\TypeChecker;
use PHPUnit\Framework\TestCase;

final class TypeCheckerTest extends TestCase
{
    private function check(string $source): void
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($source);
        $ast = $parser->parse($tokens);

        $collector = new Collector();
        $globals = $collector->collect($ast, '<test>');

        $checker = new TypeChecker($globals);
        $checker->check($ast, '<test>');
    }

    private function assertTypeError(string $source, string $messagePart): void
    {
        try {
            $this->check($source);
            $this->fail('TypeError attendue');
        } catch (TypeError $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // =========================================================================
    //  Déclarations
    // =========================================================================

    public function testSimpleDecl(): void
    {
        $this->check('<?php int $a = 5;');
        $this->assertTrue(true);
    }

    public function testDeclTypeMismatch(): void
    {
        $this->assertTypeError('<?php int $a = "hello";', 'Type incompatible');
    }

    public function testIntToFloatAllowed(): void
    {
        $this->check('<?php float $f = 5;'); // int → float OK
        $this->assertTrue(true);
    }

    public function testFloatToIntForbidden(): void
    {
        $this->assertTypeError('<?php int $i = 3.14;', 'Type incompatible');
    }

    public function testRedéclaration(): void
    {
        $this->assertTypeError('<?php int $a = 1; int $a = 2;', 'Redéclaration');
    }

    public function testVariableNotDeclared(): void
    {
        $this->assertTypeError('<?php echo $x;', "Variable '\$x' non déclarée");
    }

    // =========================================================================
    //  Expressions
    // =========================================================================

    public function testArithmetic(): void
    {
        $this->check('<?php int $a = 1 + 2;');
        $this->check('<?php float $b = 1 + 2.5;');
        $this->check('<?php int $c = 2 * 3;');
        $this->assertTrue(true);
    }

    public function testStringConcatWithDot(): void
    {
        $this->check('<?php string $s = "a" . "b";');
        $this->assertTrue(true);
    }

    public function testStringConcatWithPlusForbidden(): void
    {
        $this->assertTypeError('<?php string $s = "a" + "b";', 'Opération invalide');
    }

    public function testArithmeticOnStringForbidden(): void
    {
        $this->assertTypeError('<?php int $x = "a" + 1;', 'Opération invalide');
    }

    public function testBoolArithmeticForbidden(): void
    {
        $this->assertTypeError('<?php int $x = true + false;', 'Opération invalide');
    }

    // =========================================================================
    //  Assignation
    // =========================================================================

    public function testAssignWrongType(): void
    {
        $this->assertTypeError(
            '<?php int $a = 1; $a = "hello";',
            'Affectation incompatible'
        );
    }

    public function testCompoundAssign(): void
    {
        $this->check('<?php int $a = 1; $a += 2;');
        $this->check('<?php string $s = "a"; $s .= "b";');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Contrôle de flux
    // =========================================================================

    public function testIfRequiresBool(): void
    {
        $this->assertTypeError('<?php if (5) { }', "doit être de type 'bool'");
    }

    public function testIfWithBoolOk(): void
    {
        $this->check('<?php bool $b = true; if ($b) { }');
        $this->assertTrue(true);
    }

    public function testWhileRequiresBool(): void
    {
        $this->assertTypeError('<?php while (1) { }', "doit être de type 'bool'");
    }

    // =========================================================================
    //  Portée de bloc
    // =========================================================================

    public function testBlockScope(): void
    {
        $this->assertTypeError(
            '<?php if (true) { int $x = 5; } echo $x;',
            "Variable '\$x' non déclarée"
        );
    }

    // =========================================================================
    //  Break / Continue
    // =========================================================================

    public function testBreakOutsideLoop(): void
    {
        $this->assertTypeError('<?php break;', "'break' ne peut être utilisé");
    }

    public function testBreakInLoop(): void
    {
        $this->check('<?php while (true) { break; }');
        $this->assertTrue(true);
    }

    public function testContinueOutsideLoop(): void
    {
        $this->assertTypeError('<?php continue;', "'continue' ne peut être utilisé");
    }

    // =========================================================================
    //  Return
    // =========================================================================

    public function testReturnInFunction(): void
    {
        $this->check('<?php function f(): int { return 5; }');
        $this->assertTrue(true);
    }

    public function testReturnWrongType(): void
    {
        $this->assertTypeError(
            '<?php function f(): int { return "hello"; }',
            'Type de retour incompatible'
        );
    }

    public function testReturnValueInVoid(): void
    {
        $this->assertTypeError(
            '<?php function f(): void { return 5; }',
            "Impossible de retourner une valeur dans une fonction 'void'"
        );
    }

    public function testReturnVoidInNonVoid(): void
    {
        $this->assertTypeError(
            '<?php function f(): int { return; }',
            "'return;' dans une fonction retournant 'int'"
        );
    }

    public function testReturnOutsideFunction(): void
    {
        $this->assertTypeError('<?php return 5;', "'return' ne peut être utilisé");
    }

    // =========================================================================
    //  $this
    // =========================================================================

    public function testThisOutsideClass(): void
    {
        $this->assertTypeError(
            '<?php function f(): void { echo $this; }',
            "'\$this' ne peut être utilisé"
        );
    }

    public function testThisInMethod(): void
    {
        $this->check('<?php class Foo {
            public function f(): void { echo $this; }
        }');
        $this->assertTrue(true);
    }

    public function testThisInStaticMethodForbidden(): void
    {
        $this->assertTypeError(
            '<?php class Foo {
                public static function f(): void { echo $this; }
            }',
            "'\$this' ne peut être utilisé dans une méthode statique"
        );
    }

    // =========================================================================
    //  Types de retour et paramètres
    // =========================================================================

    public function testFunctionParamTypeMismatch(): void
    {
        $this->assertTypeError(
            '<?php function f(int $x): int { return $x; } f("hello");',
            'Argument 1'
        );
    }

    public function testFunctionArgCountMismatch(): void
    {
        $this->assertTypeError(
            '<?php function f(int $x): int { return $x; } f(1, 2);',
            'attend 1 argument'
        );
    }

    public function testUnknownFunction(): void
    {
        $this->assertTypeError('<?php foo(5);', "Fonction 'foo' inconnue");
    }

    // =========================================================================
    //  Classes
    // =========================================================================

    public function testUnknownClassInType(): void
    {
        $this->assertTypeError('<?php Foo $f;', "Classe 'Foo' inconnue");
    }

    public function testClassPropertyAccess(): void
    {
        $this->check('<?php
            class Foo { public int $x; }
            Foo $f = new Foo();
            int $y = $f->x;
        ');
        $this->assertTrue(true);
    }

    public function testUnknownProperty(): void
    {
        $this->assertTypeError(
            '<?php class Foo {} Foo $f = new Foo(); echo $f->bar;',
            "Propriété 'bar' inconnue"
        );
    }

    public function testPrivatePropertyAccessForbidden(): void
    {
        $this->assertTypeError(
            '<?php
                class Foo { private int $x; }
                Foo $f = new Foo();
                echo $f->x;
            ',
            'Accès interdit à la propriété non publique'
        );
    }

    public function testPrivatePropertyAccessInSameClassAllowed(): void
    {
        $this->check('<?php
            class Foo {
                private int $x;
                public function getX(): int { return $this->x; }
            }
        ');
        $this->assertTrue(true);
    }

    public function testNewUnknownClass(): void
    {
        $this->assertTypeError('<?php new Foo();', "Classe 'Foo' inconnue");
    }

    public function testNewInterfaceForbidden(): void
    {
        $this->assertTypeError(
            '<?php interface I {} new I();',
            "Impossible d'instancier une interface"
        );
    }

    public function testInstanceofUnknownClass(): void
    {
        $this->assertTypeError(
            '<?php class Foo {} Foo $f = new Foo(); bool $b = $f instanceof Bar;',
            "Classe 'Bar' inconnue"
        );
    }

    public function testInstanceofOk(): void
    {
        $this->check('<?php
            class Foo {}
            Foo $f = new Foo();
            bool $b = $f instanceof Foo;
        ');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  DotAccess (.)
    // =========================================================================

    public function testDotAccessOnObject(): void
    {
        $this->check('<?php
            class Foo { public int $x; }
            Foo $f = new Foo();
            int $y = $f.x;
        ');
        $this->assertTrue(true);
    }

    public function testDotAccessOnNonObject(): void
    {
        $this->assertTypeError(
            '<?php string $s = "hello"; string $x = $s.length;',
            "Accès '.' sur un 'string'"
        );
    }

    // =========================================================================
    //  Coalesce
    // =========================================================================

    public function testCoalesce(): void
    {
        $this->check('<?php ?int $a = null; int $b = $a ?? 5;');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  foreach
    // =========================================================================

  public function testForeachOk(): void
{
    $this->check('<?php
        int[] $arr = [];
        foreach ($arr as int $v) { echo $v; }
    ');
    $this->assertTrue(true);
}

    public function testForeachWrongValueType(): void
    {
        $this->assertTypeError(
            '<?php int[] $arr = []; foreach ($arr as string $v) { }',
            "Le type de la valeur de 'foreach'"
        );
    }

    public function testForeachOnNonArray(): void
    {
        $this->assertTypeError(
            '<?php int $x = 5; foreach ($x as int $v) { }',
            "L'itéré de 'foreach' doit être un tableau"
        );
    }
}