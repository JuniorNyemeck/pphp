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

final class NarrowingTest extends TestCase
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

    public function testNarrowAfterNotNull(): void
    {
        $this->check('<?php
            ?int $x = null;
            if ($x !== null) {
                int $y = $x;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowElseWithNull(): void
    {
        $this->check('<?php
            ?int $x = null;
            if ($x === null) {
                // $x est null
            } else {
                int $y = $x;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowScopeIsLimitedToBlock(): void
    {
        $this->assertError('<?php
            ?int $x = null;
            if ($x !== null) {
                int $y = $x;
            }
            int $z = $x;
        ', 'Type incompatible');
    }

    public function testNarrowInstanceof(): void
    {
        $this->check('<?php
            class Animal {}
            class Dog extends Animal { public function bark(): string { return "woof"; } }
            Animal $a = new Animal();
            if ($a instanceof Dog) {
                string $s = $a->bark();
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowInstanceofAllowsAccess(): void
    {
        $this->check('<?php
            class Animal {}
            class Dog extends Animal { public string $name; }
            Animal $a = new Animal();
            if ($a instanceof Dog) {
                string $n = $a->name;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowInAndRhs(): void
    {
        $this->check('<?php
            ?int $x = null;
            if ($x !== null && $x > 0) {
                int $y = $x;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowInAndRhsBoth(): void
    {
        $this->check('<?php
            ?int $x = null;
            ?int $y = null;
            if ($x !== null && $y !== null) {
                int $a = $x;
                int $b = $y;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowNegation(): void
    {
        $this->check('<?php
            ?int $x = null;
            if (!($x === null)) {
                int $y = $x;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowElseif(): void
    {
        $this->check('<?php
            ?int $x = null;
            if ($x === null) {
                // null
            } elseif ($x > 0) {
                int $y = $x;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowInWhileBody(): void
{
    $this->check('<?php
        ?int $x = null;
        while ($x !== null) {
            int $y = $x;
        }
    ');
    $this->assertTrue(true);
}

    public function testNarrowInstanceofInheritsMethods(): void
    {
        $this->check('<?php
            class Animal { public function breathe(): void {} }
            class Dog extends Animal { public function bark(): void {} }
            Animal $a = new Animal();
            if ($a instanceof Dog) {
                $a->bark();
                $a->breathe();
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowUnionRemoveNull(): void
    {
        $this->check('<?php
            int|string|null $x = null;
            if ($x !== null) {
                int|string $y = $x;
            }
        ');
        $this->assertTrue(true);
    }

    public function testNarrowUnionRemoveNullRejectsNull(): void
    {
        $this->assertError('<?php
            int|string|null $x = null;
            if ($x !== null) {
                int $y = $x;
            }
        ', 'Type incompatible');
    }
}