<?php

declare(strict_types=1);

namespace PPhp\Tests\Semantic;

use PPhp\Lexer\Lexer;
use PPhp\Parser\Parser;
use PPhp\Semantic\ClassHierarchy;
use PPhp\Semantic\Collector;
use PPhp\Semantic\SubtypeChecker;
use PPhp\Semantic\Type\ArrayType;
use PPhp\Semantic\Type\ClassType;
use PPhp\Semantic\Type\MixedType;
use PPhp\Semantic\Type\NullType;
use PPhp\Semantic\Type\NullableType;
use PPhp\Semantic\Type\ScalarType;
use PPhp\Semantic\Type\UnionType;
use PHPUnit\Framework\TestCase;

final class SubtypeTest extends TestCase
{
    private SubtypeChecker $checker;

    protected function setUp(): void
    {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize('<?pphp class Animal {} class Dog extends Animal {} class Cat extends Animal {} interface Pet {} class Beagle extends Dog implements Pet {}');
        $ast = $parser->parse($tokens);
        $collector = new Collector();
        $globals = $collector->collect($ast, '<test>');

        $hierarchy = new ClassHierarchy($globals);
        $this->checker = new SubtypeChecker($globals, $hierarchy);
    }

    public function testReflexivity(): void
    {
        $this->assertTrue($this->checker->isSubtypeOf(
            ScalarType::int(), ScalarType::int()
        ));
    }

    public function testIntToFloat(): void
    {
        $this->assertTrue($this->checker->isSubtypeOf(
            ScalarType::int(), ScalarType::float()
        ));
        $this->assertFalse($this->checker->isSubtypeOf(
            ScalarType::float(), ScalarType::int()
        ));
    }

    public function testMixedIsTop(): void
    {
        $m = new MixedType();
        $this->assertTrue($this->checker->isSubtypeOf(ScalarType::int(), $m));
        $this->assertTrue($this->checker->isSubtypeOf(new ClassType('Dog'), $m));
    }

    public function testNullToNullable(): void
    {
        $this->assertTrue($this->checker->isSubtypeOf(
            new NullType(),
            new NullableType(ScalarType::int())
        ));
    }

    public function testDogIsAnimal(): void
    {
        $this->assertTrue($this->checker->isSubtypeOf(
            new ClassType('Dog'),
            new ClassType('Animal')
        ));
    }

    public function testBeagleIsAnimal(): void
    {
        // Transitif : Beagle <: Dog <: Animal
        $this->assertTrue($this->checker->isSubtypeOf(
            new ClassType('Beagle'),
            new ClassType('Animal')
        ));
    }

    public function testBeagleIsPet(): void
    {
        $this->assertTrue($this->checker->isSubtypeOf(
            new ClassType('Beagle'),
            new ClassType('Pet')
        ));
    }

    public function testCatIsNotDog(): void
    {
        $this->assertFalse($this->checker->isSubtypeOf(
            new ClassType('Cat'),
            new ClassType('Dog')
        ));
    }

    public function testDogIsObject(): void
    {
        $this->assertTrue($this->checker->isSubtypeOf(
            new ClassType('Dog'),
            new ClassType('object')
        ));
    }

    public function testIntIsNotObject(): void
    {
        $this->assertFalse($this->checker->isSubtypeOf(
            ScalarType::int(),
            new ClassType('object')
        ));
    }

    public function testArrayCovariance(): void
    {
        $intArray = new ArrayType(ScalarType::int());
        $floatArray = new ArrayType(ScalarType::float());
        $this->assertTrue($this->checker->isSubtypeOf($intArray, $floatArray));

        $dogArray = new ArrayType(new ClassType('Dog'));
        $animalArray = new ArrayType(new ClassType('Animal'));
        $this->assertTrue($this->checker->isSubtypeOf($dogArray, $animalArray));
    }

    public function testUnion(): void
    {
        $u = new UnionType([ScalarType::int(), ScalarType::string()]);
        $this->assertTrue($this->checker->isSubtypeOf(ScalarType::int(), $u));
        $this->assertTrue($this->checker->isSubtypeOf(ScalarType::string(), $u));
        $this->assertFalse($this->checker->isSubtypeOf(ScalarType::bool(), $u));
    }
}