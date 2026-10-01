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
        $this->check('<?pphp int $a = 5;');
        $this->assertTrue(true);
    }

    public function testDeclTypeMismatch(): void
    {
        $this->assertTypeError('<?pphp int $a = "hello";', 'Type incompatible');
    }

    public function testIntToFloatAllowed(): void
    {
        $this->check('<?pphp float $f = 5;'); // int → float OK
        $this->assertTrue(true);
    }

    public function testFloatToIntForbidden(): void
    {
        $this->assertTypeError('<?pphp int $i = 3.14;', 'Type incompatible');
    }

    public function testRedéclaration(): void
    {
        $this->assertTypeError('<?pphp int $a = 1; int $a = 2;', 'Redéclaration');
    }

    public function testVariableNotDeclared(): void
    {
        $this->assertTypeError('<?pphp echo $x;', "Variable '\$x' non déclarée");
    }

    // =========================================================================
    //  Expressions
    // =========================================================================

    public function testArithmetic(): void
    {
        $this->check('<?pphp int $a = 1 + 2;');
        $this->check('<?pphp float $b = 1 + 2.5;');
        $this->check('<?pphp int $c = 2 * 3;');
        $this->assertTrue(true);
    }

    public function testStringConcatWithDot(): void
    {
        $this->check('<?pphp string $s = "a" . "b";');
        $this->assertTrue(true);
    }

    public function testStringConcatWithPlusForbidden(): void
    {
        $this->assertTypeError('<?pphp string $s = "a" + "b";', 'Opération invalide');
    }

    public function testArithmeticOnStringForbidden(): void
    {
        $this->assertTypeError('<?pphp int $x = "a" + 1;', 'Opération invalide');
    }

    public function testBoolArithmeticForbidden(): void
    {
        $this->assertTypeError('<?pphp int $x = true + false;', 'Opération invalide');
    }

    // =========================================================================
    //  Assignation
    // =========================================================================

    public function testAssignWrongType(): void
    {
        $this->assertTypeError(
            '<?pphp int $a = 1; $a = "hello";',
            'Affectation incompatible'
        );
    }

    public function testCompoundAssign(): void
    {
        $this->check('<?pphp int $a = 1; $a += 2;');
        $this->check('<?pphp string $s = "a"; $s .= "b";');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Contrôle de flux
    // =========================================================================

    public function testIfRequiresBool(): void
    {
        $this->assertTypeError('<?pphp if (5) { }', "doit être de type 'bool'");
    }

    public function testIfWithBoolOk(): void
    {
        $this->check('<?pphp bool $b = true; if ($b) { }');
        $this->assertTrue(true);
    }

    public function testWhileRequiresBool(): void
    {
        $this->assertTypeError('<?pphp while (1) { }', "doit être de type 'bool'");
    }

    // =========================================================================
    //  Portée de bloc
    // =========================================================================

    public function testBlockScope(): void
    {
        $this->assertTypeError(
            '<?pphp if (true) { int $x = 5; } echo $x;',
            "Variable '\$x' non déclarée"
        );
    }

    // =========================================================================
    //  Break / Continue
    // =========================================================================

    public function testBreakOutsideLoop(): void
    {
        $this->assertTypeError('<?pphp break;', "'break' ne peut être utilisé");
    }

    public function testBreakInLoop(): void
    {
        $this->check('<?pphp while (true) { break; }');
        $this->assertTrue(true);
    }

    public function testContinueOutsideLoop(): void
    {
        $this->assertTypeError('<?pphp continue;', "'continue' ne peut être utilisé");
    }

    // =========================================================================
    //  Return
    // =========================================================================

    public function testReturnInFunction(): void
    {
        $this->check('<?pphp function f(): int { return 5; }');
        $this->assertTrue(true);
    }

    public function testReturnWrongType(): void
    {
        $this->assertTypeError(
            '<?pphp function f(): int { return "hello"; }',
            'Type de retour incompatible'
        );
    }

    public function testReturnValueInVoid(): void
    {
        $this->assertTypeError(
            '<?pphp function f(): void { return 5; }',
            "Impossible de retourner une valeur dans une fonction 'void'"
        );
    }

    public function testReturnVoidInNonVoid(): void
    {
        $this->assertTypeError(
            '<?pphp function f(): int { return; }',
            "'return;' dans une fonction retournant 'int'"
        );
    }

    public function testReturnOutsideFunction(): void
    {
        $this->assertTypeError('<?pphp return 5;', "'return' ne peut être utilisé");
    }

    // =========================================================================
    //  $this
    // =========================================================================

    public function testThisOutsideClass(): void
    {
        $this->assertTypeError(
            '<?pphp function f(): void { echo $this; }',
            "'\$this' ne peut être utilisé"
        );
    }

    public function testThisInMethod(): void
    {
        $this->check('<?pphp class Foo {
            public function f(): void { echo $this; }
        }');
        $this->assertTrue(true);
    }

    public function testThisInStaticMethodForbidden(): void
    {
        $this->assertTypeError(
            '<?pphp class Foo {
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
        '<?pphp function f(int $x): int { return $x; } f("hello");',
        'Aucune surcharge'
    );
}

    public function testFunctionArgCountMismatch(): void
    {
        $this->assertTypeError(
            '<?pphp function f(int $x): int { return $x; } f(1, 2);',
            'attend 1 argument'
        );
    }

    public function testUnknownFunction(): void
    {
        $this->assertTypeError('<?pphp foo(5);', "Fonction 'foo' inconnue");
    }

    // =========================================================================
    //  Classes
    // =========================================================================

    public function testUnknownClassInType(): void
    {
        $this->assertTypeError('<?pphp Foo $f;', "Classe 'Foo' inconnue");
    }

    public function testClassPropertyAccess(): void
    {
        $this->check('<?pphp
            class Foo { public int $x; }
            Foo $f = new Foo();
            int $y = $f->x;
        ');
        $this->assertTrue(true);
    }

    public function testUnknownProperty(): void
    {
        $this->assertTypeError(
            '<?pphp class Foo {} Foo $f = new Foo(); echo $f->bar;',
            "Propriété 'bar' inconnue"
        );
    }

    public function testPrivatePropertyAccessForbidden(): void
    {
        $this->assertTypeError(
            '<?pphp
                class Foo { private int $x; }
                Foo $f = new Foo();
                echo $f->x;
            ',
            'Accès interdit à la propriété non publique'
        );
    }

    public function testPrivatePropertyAccessInSameClassAllowed(): void
    {
        $this->check('<?pphp
            class Foo {
                private int $x;
                public function getX(): int { return $this->x; }
            }
        ');
        $this->assertTrue(true);
    }

    public function testNewUnknownClass(): void
    {
        $this->assertTypeError('<?pphp new Foo();', "Classe 'Foo' inconnue");
    }

    public function testNewInterfaceForbidden(): void
    {
        $this->assertTypeError(
            '<?pphp interface I {} new I();',
            "Impossible d'instancier une interface"
        );
    }

    public function testInstanceofUnknownClass(): void
    {
        $this->assertTypeError(
            '<?pphp class Foo {} Foo $f = new Foo(); bool $b = $f instanceof Bar;',
            "Classe 'Bar' inconnue"
        );
    }

    public function testInstanceofOk(): void
    {
        $this->check('<?pphp
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
        $this->check('<?pphp
            class Foo { public int $x; }
            Foo $f = new Foo();
            int $y = $f.x;
        ');
        $this->assertTrue(true);
    }

    public function testDotAccessOnNonObject(): void
    {
        $this->assertTypeError(
            '<?pphp string $s = "hello"; string $x = $s.length;',
            "Accès '.' sur un 'string'"
        );
    }

    // =========================================================================
    //  Coalesce
    // =========================================================================

    public function testCoalesce(): void
    {
        $this->check('<?pphp ?int $a = null; int $b = $a ?? 5;');
        $this->assertTrue(true);
    }

    // =========================================================================
    //  foreach
    // =========================================================================

  /* public function testForeachOk(): void
{
    $this->check('<?pphp
        int[] $arr = [];
        foreach ($arr as int $v) { echo $v; }
    ');
    $this->assertTrue(true);
}

    public function testForeachWrongValueType(): void
    {
        $this->assertTypeError(
            '<?pphp int[] $arr = []; foreach ($arr as string $v) { }',
            "Le type de la valeur de 'foreach'"
        );
    }

    public function testForeachOnNonArray(): void
    {
        $this->assertTypeError(
            '<?pphp int $x = 5; foreach ($x as int $v) { }',
            "L'itéré de 'foreach' doit être un tableau"
        );
    } */



        public function testForeachOk(): void
{
    $this->check('<?pphp
        int[] $arr = [];
        foreach ($arr as int $v) { echo $v; }
    ');
    $this->assertTrue(true);
}

public function testForeachWrongValueType(): void
{
    $this->assertTypeError(
        '<?pphp int[] $arr = []; foreach ($arr as string $v) { }',
        "Le type de la valeur de 'foreach'"
    );
}

public function testForeachOnNonArray(): void
{
    $this->assertTypeError(
        '<?pphp int $x = 5; foreach ($x as int $v) { }',
        "L'itéré de 'foreach' doit être un tableau"
    );
}

public function testForeachMixedArrayRejected(): void
{
    // Un tableau mixed[] ne peut pas être itéré avec un type précis
    $this->assertTypeError(
        '<?pphp array $arr = []; foreach ($arr as int $v) { }',
        "Impossible de garantir"
    );
}

public function testForeachWithKey(): void
{
    $this->check('<?pphp
        string[] $arr = [];
        foreach ($arr as int $k => string $v) { echo $k; echo $v; }
    ');
    $this->assertTrue(true);
}

public function testForeachInvalidKeyType(): void
{
    $this->assertTypeError(
        '<?pphp string[] $arr = []; foreach ($arr as bool $k => string $v) { }',
        "clé de 'foreach' doit être 'int' ou 'string'"
    );
}

public function testForeachVariableScopeIsLimited(): void
{
    // $v n'existe pas après le foreach
    $this->assertTypeError(
        '<?pphp int[] $arr = []; foreach ($arr as int $v) { } echo $v;',
        "Variable '\$v' non déclarée"
    );
}

public function testForeachValueSubtypeAllowed(): void
{
    // int[] itéré avec float : int <: float, OK
    $this->check('<?pphp
        int[] $arr = [];
        foreach ($arr as float $v) { echo $v; }
    ');
    $this->assertTrue(true);
}


public function testAccessInheritedProperty(): void
{
    $this->check('<?pphp
        class Animal { public string $name; }
        class Dog extends Animal {}
        Dog $d = new Dog();
        string $n = $d->name;
    ');
    $this->assertTrue(true);
}

public function testAccessInheritedMethod(): void
{
    $this->check('<?pphp
        class Animal { public function speak(): string { return "..."; } }
        class Dog extends Animal {}
        Dog $d = new Dog();
        string $s = $d->speak();
    ');
    $this->assertTrue(true);
}

public function testProtectedAccessibleInSubclass(): void
{
    $this->check('<?pphp
        class Animal { protected string $name; }
        class Dog extends Animal {
            public function getName(): string { return $this->name; }
        }
    ');
    $this->assertTrue(true);
}

public function testProtectedNotAccessibleFromOutside(): void
{
    $this->assertTypeError('<?pphp
        class Animal { protected string $name; }
        Animal $a = new Animal();
        string $n = $a->name;
    ', 'Accès interdit');
}

public function testPrivateNotAccessibleInSubclass(): void
{
    $this->assertTypeError('<?pphp
        class Animal { private string $name; }
        class Dog extends Animal {
            public function getName(): string { return $this->name; }
        }
    ', 'Accès interdit');
}

public function testSubtypeArgAccepted(): void
{
    // Dog <: Animal, donc passer un Dog où Animal est attendu fonctionne
    $this->check('<?pphp
        class Animal {}
        class Dog extends Animal {}
        function f(Animal $a): void {}
        Dog $d = new Dog();
        f($d);
    ');
    $this->assertTrue(true);
}

public function testSupertypeArgRejected(): void
{
    $this->assertTypeError('<?pphp
        class Animal {}
        class Dog extends Animal {}
        function f(Dog $d): void {}
        Animal $a = new Animal();
        f($a);
    ', 'Aucune surcharge');
}
}