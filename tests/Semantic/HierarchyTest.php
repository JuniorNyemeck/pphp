<?php

declare(strict_types=1);

namespace PPhp\Tests\Semantic;

use PPhp\Error\TypeError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\ClassHierarchy;
use PPhp\Semantic\Collector;
use PPhp\Semantic\GlobalScope;
use PHPUnit\Framework\TestCase;

final class HierarchyTest extends TestCase
{
    private function collect(string $source): GlobalScope
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($source);
        $ast = $parser->parse($tokens);

        $collector = new Collector();
        $globals = $collector->collect($ast, '<test>');

        $hierarchy = new ClassHierarchy($globals);
        $hierarchy->validate('<test>');

        return $globals;
    }

    private function assertValidationError(string $source, string $messagePart): void
    {
        try {
            $this->collect($source);
            $this->fail('TypeError attendue');
        } catch (TypeError $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    public function testSimpleInheritance(): void
    {
        $this->collect('<?php class Animal {} class Dog extends Animal {}');
        $this->assertTrue(true);
    }

    public function testTransitiveInheritance(): void
    {
        $this->collect('<?php
            class A {}
            class B extends A {}
            class C extends B {}
        ');
        $this->assertTrue(true);
    }

    public function testUnknownParent(): void
    {
        $this->assertValidationError(
            '<?php class Foo extends Bar {}',
            "Classe parente 'Bar' inconnue"
        );
    }

    public function testClassExtendingInterface(): void
    {
        $this->assertValidationError(
            '<?php interface I {} class Foo extends I {}',
            "ne peut pas étendre l'interface"
        );
    }

    public function testInterfaceExtendingClass(): void
{
    $this->assertValidationError(
        '<?php class A {} interface I extends A {}',
        "Une interface ne peut étendre qu'une interface"
    );
}

    public function testUnknownInterface(): void
    {
        $this->assertValidationError(
            '<?php class Foo implements Bar {}',
            "Interface 'Bar' inconnue"
        );
    }

    public function testImplementingNonInterface(): void
    {
        $this->assertValidationError(
            '<?php class A {} class Foo implements A {}',
            "n'est pas une interface"
        );
    }

    public function testInheritanceCycle(): void
    {
        $this->assertValidationError(
            '<?php class A extends B {} class B extends A {}',
            "Cycle d'héritage"
        );
    }

    public function testSelfInheritanceCycle(): void
    {
        $this->assertValidationError(
            '<?php class A extends A {}',
            "Cycle d'héritage"
        );
    }

    public function testSubtypeTransitivity(): void
    {
        $globals = $this->collect('<?php
            class A {}
            class B extends A {}
            class C extends B {}
        ');
        $hierarchy = new ClassHierarchy($globals);

        $this->assertTrue($hierarchy->isSubclassOf('C', 'A'));
        $this->assertTrue($hierarchy->isSubclassOf('C', 'B'));
        $this->assertTrue($hierarchy->isSubclassOf('B', 'A'));
        $this->assertFalse($hierarchy->isSubclassOf('A', 'C'));
    }

    public function testInterfaceImplementation(): void
    {
        $globals = $this->collect('<?php
            interface I {}
            class Foo implements I {}
        ');
        $hierarchy = new ClassHierarchy($globals);
        $this->assertTrue($hierarchy->implementsInterface('Foo', 'I'));
    }

    public function testInterfaceInheritanceTransitive(): void
    {
        $globals = $this->collect('<?php
            interface I {}
            interface J extends I {}
            class Foo implements J {}
        ');
        $hierarchy = new ClassHierarchy($globals);
        $this->assertTrue($hierarchy->implementsInterface('Foo', 'I'));
        $this->assertTrue($hierarchy->implementsInterface('Foo', 'J'));
    }
}