<?php

declare(strict_types=1);

namespace PPhp\Tests\Semantic;

use PPhp\Error\TypeError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\Collector;
use PHPUnit\Framework\TestCase;

final class OverloadTest extends TestCase
{
    private function collect(string $source): \PPhp\Semantic\GlobalScope
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($source);
        $ast = $parser->parse($tokens);

        $collector = new Collector();
        return $collector->collect($ast, '<test>');
    }

    private function assertCollectError(string $source, string $messagePart): void
    {
        try {
            $this->collect($source);
            $this->fail('TypeError attendue');
        } catch (TypeError $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // =========================================================================
    //  Surcharge basique
    // =========================================================================

    public function testOverloadWithDifferentScalarTypes(): void
    {
        $g = $this->collect('<?php
            function f(int $x): void {}
            function f(string $x): void {}
        ');
        $this->assertCount(2, $g->getFunctions('f'));
    }

    public function testOverloadMethodWithDifferentScalarTypes(): void
    {
        $g = $this->collect('<?php
            class A {
                public function f(int $x): void {}
                public function f(string $x): void {}
            }
        ');
        $this->assertCount(2, $g->getClass('A')->methods['f']);
    }

    public function testOverloadConstructor(): void
    {
        $g = $this->collect('<?php
            class A {
                public function __construct(int $x) {}
                public function __construct(string $x) {}
            }
        ');
        $this->assertCount(2, $g->getClass('A')->methods['__construct']);
    }

    // =========================================================================
    //  Unicité
    // =========================================================================

    public function testDuplicateSignature(): void
    {
        $this->assertCollectError('<?php
            function f(int $x): void {}
            function f(int $y): void {}
        ', 'signature identique');
    }

    public function testDuplicateSignatureMethods(): void
    {
        $this->assertCollectError('<?php
            class A {
                public function f(int $x): void {}
                public function f(int $y): void {}
            }
        ', 'signature identique');
    }

    // =========================================================================
    //  Disjonction
    // =========================================================================

    public function testIntAndFloatNotDisjoint(): void
    {
        $this->assertCollectError('<?php
            function f(int $x): void {}
            function f(float $x): void {}
        ', 'ne sont pas disjointes');
    }

    public function testMixedNotDisjoint(): void
    {
        $this->assertCollectError('<?php
            function f(mixed $x): void {}
            function f(int $x): void {}
        ', 'ne sont pas disjointes');
    }

    public function testNullableIntNotDisjoint(): void
    {
        $this->assertCollectError('<?php
            function f(?int $x): void {}
            function f(int $x): void {}
        ', 'ne sont pas disjointes');
    }

    public function testIntAndStringDisjoint(): void
    {
        $g = $this->collect('<?php
            function f(int $x): void {}
            function f(string $x): void {}
        ');
        $this->assertCount(2, $g->getFunctions('f'));
    }

    public function testNullableIntAndStringDisjoint(): void
    {
        $g = $this->collect('<?php
            function f(?int $x): void {}
            function f(string $x): void {}
        ');
        $this->assertCount(2, $g->getFunctions('f'));
    }

    public function testUnionDisjoint(): void
    {
        $g = $this->collect('<?php
            function f(int|string $x): void {}
            function f(bool $x): void {}
        ');
        $this->assertCount(2, $g->getFunctions('f'));
    }

    public function testUnionNotDisjoint(): void
    {
        $this->assertCollectError('<?php
            function f(int|string $x): void {}
            function f(int $x): void {}
        ', 'ne sont pas disjointes');
    }

    // =========================================================================
    //  Nombre de paramètres
    // =========================================================================

    public function testDifferentParamCountsRejected(): void
    {
        $this->assertCollectError('<?php
            function f(int $x): void {}
            function f(int $x, int $y): void {}
        ', 'même nombre de paramètres');
    }

    // =========================================================================
    //  Visibilité et static
    // =========================================================================

    public function testDifferentVisibilityRejected(): void
    {
        $this->assertCollectError('<?php
            class A {
                public function f(int $x): void {}
                protected function f(string $x): void {}
            }
        ', 'même visibilité');
    }

    public function testDifferentStaticRejected(): void
    {
        $this->assertCollectError('<?php
            class A {
                public function f(int $x): void {}
                public static function f(string $x): void {}
            }
        ', 'même visibilité et la même staticité');
    }

    // =========================================================================
    //  Classes
    // =========================================================================

    public function testOverloadWithClassesDisjoint(): void
    {
        $g = $this->collect('<?php
            class Dog {}
            class Cat {}
            function f(Dog $x): void {}
            function f(Cat $x): void {}
        ');
        $this->assertCount(2, $g->getFunctions('f'));
    }

    public function testOverloadWithParentChildNotDisjoint(): void
    {
        $this->assertCollectError('<?php
            class Animal {}
            class Dog extends Animal {}
            function f(Animal $x): void {}
            function f(Dog $x): void {}
        ', 'ne sont pas disjointes');
    }
}