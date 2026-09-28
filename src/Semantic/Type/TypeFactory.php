<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

use PPhp\Parser\Node\TypeNode;

final class TypeFactory
{
    /**
     * Convertit un TypeNode en Type.
     *
     * @param string|null $currentClass Nom de la classe courante pour résoudre self/parent/static
     * @param string|null $parentClass  Nom de la classe parente
     */
    public static function fromNode(
        TypeNode $node,
        ?string $currentClass = null,
        ?string $parentClass = null,
    ): Type {
        // Gestion des types de base
        $base = self::resolveBase($node->name, $currentClass, $parentClass);

        // Unions : A|B|C
        if (!empty($node->union)) {
            $members = [$base];
            foreach ($node->union as $u) {
                $members[] = self::fromNode($u, $currentClass, $parentClass);
            }
            return self::applyModifiers(new UnionType($members), $node);
        }

        return self::applyModifiers($base, $node);
    }

    private static function resolveBase(
        string $name,
        ?string $currentClass,
        ?string $parentClass,
    ): Type {
        $lower = strtolower($name);

        // Scalaires
        if (in_array($lower, ['int', 'float', 'bool', 'string'], true)) {
            return new ScalarType($lower);
        }

        // Types spéciaux
        return match ($lower) {
            'array'    => new ArrayType(new MixedType()),
            'mixed'    => new MixedType(),
            'void'     => new VoidType(),
            'never'    => new NeverType(),
            'null'     => new NullType(),
            'object'   => new ClassType('object'),
            'callable' => new ClassType('callable'),
            'iterable' => new ArrayType(new MixedType()),
            'self'     => $currentClass !== null
                ? new ClassType($currentClass)
                : new ClassType('self'),
            'parent'   => $parentClass !== null
                ? new ClassType($parentClass)
                : new ClassType('parent'),
            default    => new ClassType($name),
        };
    }

    private static function applyModifiers(Type $base, TypeNode $node): Type
    {
        // Tableaux : T[]  /  T[][]
        for ($i = 0; $i < $node->arrayDepth; $i++) {
            $base = new ArrayType($base);
        }

        // Nullable : ?T
        if ($node->nullable) {
            if ($base instanceof NullableType || $base instanceof NullType) {
                return $base;
            }
            return new NullableType($base);
        }

        return $base;
    }
}