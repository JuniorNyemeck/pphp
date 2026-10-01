<?php

declare(strict_types=1);

namespace PPhp\Tests\Semantic;

use PPhp\Error\TypeError;
use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\Collector;
use PHPUnit\Framework\TestCase;

final class CollectorTest extends TestCase
{
    private Lexer $lexer;
    private Parser $parser;
    private Collector $collector;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->collector = new Collector();
    }

    private function collect(string $source): \PPhp\Semantic\GlobalScope
    {
        $tokens = $this->lexer->tokenize($source);
        $ast = $this->parser->parse($tokens);
        return $this->collector->collect($ast, '<test>');
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
    //  Fonctions
    // =========================================================================

    public function testCollectSimpleFunction(): void
    {
        $g = $this->collect('<?pphp function f(int $x): int { return $x; }');
        $this->assertTrue($g->functionExists('f'));
        $this->assertSame('int', (string) $g->getFunction('f')->returnType);
    }

    public function testDuplicateFunction(): void
    {
        $this->assertCollectError(
            '<?pphp function f(): void {} function f(): void {}',
            "signature identique"
        );
    }

    public function testFunctionMissingReturnTypeIsParserError(): void
    {
        // Le parser refuse déjà `function f() {}` sans type de retour.
        // Donc on teste juste que ça lève bien une erreur.
        $this->expectException(\Throwable::class);
        $this->collect('<?pphp function f() {}');
    }

    // =========================================================================
    //  Classes
    // =========================================================================

    public function testCollectSimpleClass(): void
    {
        $g = $this->collect('<?pphp class Foo {}');
        $this->assertTrue($g->classExists('Foo'));
    }

    public function testCollectClassWithParent(): void
    {
        $g = $this->collect('<?pphp class Bar {} class Foo extends Bar {}');
        $this->assertSame('Bar', $g->getClass('Foo')->parent);
    }

    public function testCollectInterface(): void
    {
        $g = $this->collect('<?pphp interface I {}');
        $this->assertTrue($g->classExists('I'));
        $this->assertTrue($g->getClass('I')->isInterface);
    }

    public function testDuplicateClass(): void
    {
        $this->assertCollectError(
            '<?pphp class Foo {} class Foo {}',
            "Redéclaration de la classe 'Foo'"
        );
    }

    // =========================================================================
    //  Propriétés
    // =========================================================================

    public function testCollectProperty(): void
    {
        $g = $this->collect('<?pphp class Foo { public int $x; }');
        $info = $g->getClass('Foo');
        $this->assertArrayHasKey('x', $info->properties);
        $this->assertSame('int', (string) $info->properties['x']->type);
    }

    public function testCollectMultipleProperties(): void
    {
        $g = $this->collect('<?pphp class Foo { public int $a, $b, $c; }');
        $info = $g->getClass('Foo');
        $this->assertArrayHasKey('a', $info->properties);
        $this->assertArrayHasKey('b', $info->properties);
        $this->assertArrayHasKey('c', $info->properties);
    }

    public function testDuplicateProperty(): void
    {
        $this->assertCollectError(
            '<?pphp class Foo { public int $x; public string $x; }',
            "Redéclaration de la propriété 'x'"
        );
    }

    public function testPropertyTypeArray(): void
    {
        $g = $this->collect('<?pphp class Foo { public string[] $names; }');
        $info = $g->getClass('Foo');
        $this->assertSame('string[]', (string) $info->properties['names']->type);
    }

    public function testPropertyTypeNullable(): void
    {
        $g = $this->collect('<?pphp class Foo { public ?int $x; }');
        $info = $g->getClass('Foo');
        $this->assertSame('?int', (string) $info->properties['x']->type);
    }

    // =========================================================================
    //  Méthodes
    // =========================================================================

    public function testCollectMethod(): void
    {
        $g = $this->collect('<?pphp class Foo { public function getX(): int { return 1; } }');
        $info = $g->getClass('Foo');
        $this->assertArrayHasKey('getX', $info->methods);
        $this->assertSame('int', (string) $info->methods['getX'][0]->returnType);
    }

    public function testDuplicateMethod(): void
    {
        $this->assertCollectError(
            '<?pphp class Foo {
                public function m(): void {}
                public function m(): void {}
            }',
            "signature identique"
        );
    }

    public function testMethodIsAbstract(): void
    {
        $g = $this->collect('<?pphp interface I { public function m(): int; }');
        $info = $g->getClass('I');
        $this->assertTrue($info->methods['m'][0]->isAbstract());
    }

    // =========================================================================
    //  Types complexes
    // =========================================================================

    public function testSelfResolvesToCurrentClass(): void
    {
        $g = $this->collect('<?pphp class Foo { public function copy(): self { return $this; } }');
        $info = $g->getClass('Foo');
        $this->assertSame('Foo', (string) $info->methods['copy'][0]->returnType);
    }

    public function testParentResolvesToParentClass(): void
{
    $g = $this->collect('<?pphp class Bar {} class Foo extends Bar {
        public function p(): parent { return $this; }
    }');
    $info = $g->getClass('Foo');
    $this->assertSame('Bar', (string) $info->methods['p'][0]->returnType);
}


}