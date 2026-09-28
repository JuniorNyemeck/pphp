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

/**
 * Vérifie la relation de sous-typage T1 <: T2.
 *
 * Version 3a : réduite aux règles de base.
 * L'héritage de classes sera ajouté à l'étape 4.
 */
final class SubtypeChecker
{
    public function __construct(private readonly GlobalScope $globals) {}

    public function isSubtypeOf(Type $sub, Type $sup): bool
    {
        // Réflexivité
        if ($sub->equals($sup)) {
            return true;
        }

        // never <: T (pour tout T)
        if ($sub instanceof NeverType) {
            return true;
        }

        // T <: mixed (pour tout T)
        if ($sup instanceof MixedType) {
            return true;
        }

        // void n'est sous-type de rien sauf void (déjà couvert par réflexivité)
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

        // int <: float (règle spéciale)
        if ($sub instanceof ScalarType && $sup instanceof ScalarType) {
            if ($sub->name === ScalarType::INT && $sup->name === ScalarType::FLOAT) {
                return true;
            }
        }

        // ?T <: ?U si T <: U
        if ($sub instanceof NullableType && $sup instanceof NullableType) {
            return $this->isSubtypeOf($sub->inner, $sup->inner);
        }

        // T <: ?U si T <: U
        if ($sup instanceof NullableType) {
            return $this->isSubtypeOf($sub, $sup->inner);
        }

        // T[] <: U[] si T <: U
        if ($sub instanceof ArrayType && $sup instanceof ArrayType) {
            return $this->isSubtypeOf($sub->element, $sup->element);
        }

        // T <: A|B si T <: A ou T <: B
        if ($sup instanceof UnionType) {
            foreach ($sup->members as $m) {
                if ($this->isSubtypeOf($sub, $m)) {
                    return true;
                }
            }
            return false;
        }

        // A|B <: T si A <: T ET B <: T
        if ($sub instanceof UnionType) {
            foreach ($sub->members as $m) {
                if (!$this->isSubtypeOf($m, $sup)) {
                    return false;
                }
            }
            return true;
        }

        // Classes : à enrichir à l'étape 4 (héritage, interfaces)
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
        // 'object' est super-type de toute classe
        if ($sup === 'object') {
            return $this->globals->classExists($sub);
        }
        // Héritage : à implémenter à l'étape 4
        // Pour 3a, on regarde juste le parent direct.
        $class = $this->globals->getClass($sub);
        if ($class === null) {
            return false;
        }
        if ($class->parent === $sup) {
            return true;
        }
        // Interfaces : idem étape 4
        return false;
    }
}