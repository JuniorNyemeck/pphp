<?php

declare(strict_types=1);

namespace PPhp\Tests\Codegen;

use PPhp\Codegen\NameMangler;
use PPhp\Parser\Node\TypeNode;
use PHPUnit\Framework\TestCase;

final class NameManglerTest extends TestCase
{
    private NameMangler $mangler;

    protected function setUp(): void
    {
        $this->mangler = new NameMangler();
    }

    private function t(string $name, bool $nullable = false, int $arrayDepth = 0): TypeNode
    {
        return new TypeNode(1, 1, $name, $nullable, $arrayDepth);
    }

    public function testSimpleInt(): void
    {
        $this->assertSame('int', $this->mangler->typeSignature($this->t('int')));
    }

    public function testNullableInt(): void
    {
        $this->assertSame('n_int', $this->mangler->typeSignature(
            $this->t('int', nullable: true)
        ));
    }

    public function testArrayInt(): void
    {
        $this->assertSame('a_int', $this->mangler->typeSignature(
            $this->t('int', arrayDepth: 1)
        ));
    }

    public function testNestedArray(): void
    {
        $this->assertSame('a2_int', $this->mangler->typeSignature(
            $this->t('int', arrayDepth: 2)
        ));
    }

    public function testClassType(): void
    {
        $this->assertSame('Dog', $this->mangler->typeSignature($this->t('Dog')));
    }

    public function testQualifiedClass(): void
    {
        $this->assertSame('Foo_Bar', $this->mangler->typeSignature($this->t('Foo\\Bar')));
    }

    public function testUnion(): void
    {
        $union = new TypeNode(1, 1, 'int', false, 0, [
            new TypeNode(1, 1, 'string', false, 0),
        ]);
        $this->assertSame('u_int_string', $this->mangler->typeSignature($union));
    }
}