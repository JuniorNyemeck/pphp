<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Semantic\Type\ArrayType;
use PPhp\Semantic\Type\ClassType;
use PPhp\Semantic\Type\MixedType;
use PPhp\Semantic\Type\NeverType;
use PPhp\Semantic\Type\NullType;
use PPhp\Semantic\Type\NullableType;
use PPhp\Semantic\Type\ScalarType;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\UnionType;
use PPhp\Semantic\Type\VoidType;

final class SubtypeChecker
{
    private readonly ClassHierarchy $hierarchy;

    public function __construct(
        private readonly GlobalScope $globals,
        ?ClassHierarchy $hierarchy = null,
    ) {
        $this->hierarchy = $hierarchy ?? new ClassHierarchy($globals);
    }

    public function isSubtypeOf(Type $sub, Type $sup): bool
    {
        // Réflexivité
        if ($sub->equals($sup)) {
            return true;
        }

        // never <: T
        if ($sub instanceof NeverType) {
            return true;
        }

        // T <: mixed
        if ($sup instanceof MixedType) {
            return true;
        }

        // void
        if ($sup instanceof VoidType && !$sub instanceof VoidType) {
            return false;
        }

        // null <: ?T et null <: T|null
        if ($sub instanceof NullType) {
            if ($sup instanceof NullableType) {
                return true;
            }
            if ($sup instanceof UnionType) {
                foreach ($sup->members as $m) {
                    if ($m instanceof NullType) {
                        return true;
                    }
                }
            }
            return false;
        }

        // int <: float
        if ($sub instanceof ScalarType && $sup instanceof ScalarType) {
            if ($sub->name === ScalarType::INT && $sup->name === ScalarType::FLOAT) {
                return true;
            }
        }

        // ?T <: ?U
        if ($sub instanceof NullableType && $sup instanceof NullableType) {
            return $this->isSubtypeOf($sub->inner, $sup->inner);
        }

        // T <: ?U
        if ($sup instanceof NullableType) {
            return $this->isSubtypeOf($sub, $sup->inner);
        }

        // T[] <: U[]
        if ($sub instanceof ArrayType && $sup instanceof ArrayType) {
            return $this->isSubtypeOf($sub->element, $sup->element);
        }

        // T <: A|B
        if ($sup instanceof UnionType) {
            foreach ($sup->members as $m) {
                if ($this->isSubtypeOf($sub, $m)) {
                    return true;
                }
            }
            return false;
        }

        // A|B <: T
        if ($sub instanceof UnionType) {
            foreach ($sub->members as $m) {
                if (!$this->isSubtypeOf($m, $sup)) {
                    return false;
                }
            }
            return true;
        }

        // Classes et interfaces
        if ($sub instanceof ClassType && $sup instanceof ClassType) {
            return $this->isClassSubtypeOf($sub->name, $sup->name);
        }

        return false;
    }

    private function isClassSubtypeOf(string $sub, string $sup): bool
    {
        if ($sub === $sup) {
            return true;
        }

        // Cas spéciaux des types builtin
        if ($sup === 'object') {
            // Toute classe connue est <: object
            return $this->globals->classExists($sub);
        }

        // Vérifier si l'un des deux n'est pas une classe connue
        if (!$this->globals->classExists($sub)) {
            return false;
        }

        // Relation d'héritage complète
        return $this->hierarchy->isSubclassOf($sub, $sup);
    }
}