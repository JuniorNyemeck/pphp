<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Semantic\Type\MixedType;
use PPhp\Semantic\Type\ScalarType;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\UnionType;

/**
 * Règles de typage des opérateurs binaires et unaires.
 *
 * Chaque opérateur a une méthode `check` qui, étant donné les types
 * des opérandes, retourne le type du résultat ou null (incompatible).
 */
final class OperatorTypeTable
{
    /**
     * Type du résultat d'une opération binaire.
     * Retourne null si l'opération est invalide.
     */
    public function binaryResultType(string $op, Type $left, Type $right): ?Type
    {
        return match ($op) {
            '+', '-', '*', '/', '%', '**' => $this->arithmetic($op, $left, $right),
            '.'                          => $this->concat($left, $right),
            '<', '<=', '>', '>=', '<=>'  => $this->comparison($op, $left, $right),
            '===', '!=='                 => $this->strictEquality($left, $right),
            '==', '!='                   => $this->looseEquality($left, $right),
            '&&', '||', 'and', 'or'      => $this->logical($op, $left, $right),
            '&', '|', '^', '<<', '>>'    => $this->bitwise($op, $left, $right),
            '??'                         => $this->coalesce($left, $right),
            default                      => null,
        };
    }

    private function arithmetic(string $op, Type $left, Type $right): ?Type
    {
        // % et << >> ne fonctionnent que sur int
        if ($op === '%') {
            if ($this->isInt($left) && $this->isInt($right)) {
                return ScalarType::int();
            }
            return null;
        }

        // ** : int ** int → int ; int ** float → float ; float ** int/float → float
        if ($op === '**') {
            if (($this->isInt($left) || $this->isFloat($left))
                && ($this->isInt($right) || $this->isFloat($right))) {
                if ($this->isInt($left) && $this->isInt($right)) {
                    return ScalarType::int();
                }
                return ScalarType::float();
            }
            return null;
        }

        // +, -, *, /
        if (!$this->isNumeric($left) || !$this->isNumeric($right)) {
            return null;
        }
        if ($this->isFloat($left) || $this->isFloat($right)) {
            return ScalarType::float();
        }
        // / sur int/int : PHP retourne float ou int, mais on simplifie : float
        if ($op === '/') {
            return ScalarType::float();
        }
        return ScalarType::int();
    }

    private function concat(Type $left, Type $right): ?Type
    {
        if ($this->isString($left) && $this->isString($right)) {
            return ScalarType::string();
        }
        return null;
    }

    private function comparison(string $op, Type $left, Type $right): ?Type
    {
        // Comparaison entre types compatibles : numeric <-> numeric, string <-> string
        if ($this->areComparable($left, $right)) {
            return ScalarType::bool();
        }
        return null;
    }

    private function strictEquality(Type $left, Type $right): ?Type
    {
        // Types compatibles exigés (l'un sous-type de l'autre)
        // On délègue cette vérification au TypeChecker, qui a accès au SubtypeChecker.
        // Ici on accepte si les types sont "proches".
        if ($this->areTypeCompatible($left, $right)) {
            return ScalarType::bool();
        }
        return null;
    }

    private function looseEquality(Type $left, Type $right): ?Type
    {
        // == et != acceptent tout
        return ScalarType::bool();
    }

    private function logical(string $op, Type $left, Type $right): ?Type
    {
        if ($this->isBool($left) && $this->isBool($right)) {
            return ScalarType::bool();
        }
        return null;
    }

    private function bitwise(string $op, Type $left, Type $right): ?Type
    {
        if ($op === '<<' || $op === '>>') {
            if ($this->isInt($left) && $this->isInt($right)) {
                return ScalarType::int();
            }
            return null;
        }
        if ($this->isInt($left) && $this->isInt($right)) {
            return ScalarType::int();
        }
        // & | ^ sur bool → bool (PHP)
        if ($this->isBool($left) && $this->isBool($right)) {
            return ScalarType::bool();
        }
        return null;
    }

    private function coalesce(Type $left, Type $right): ?Type
    {
        // $a ?? $b : si $a est ?T, résultat = T|typeof($b)
        // On simplifie : résultat = union des deux types si différents
        if ($left->equals($right)) {
            return $left;
        }
        // Si left est nullable, on retire la partie null
        if ($left instanceof \PPhp\Semantic\Type\NullableType) {
            $inner = $left->inner;
            if ($inner->equals($right)) {
                return $inner;
            }
            return new UnionType([$inner, $right]);
        }
        if ($left instanceof \PPhp\Semantic\Type\NullType) {
            return $right;
        }
        // Sinon, union naïve
        return new UnionType([$left, $right]);
    }

    /**
     * Type du résultat d'une opération unaire.
     */
    public function unaryResultType(string $op, Type $operand): ?Type
    {
        return match ($op) {
            '-'  => ($this->isInt($operand) || $this->isFloat($operand))
                        ? $operand
                        : null,
            '+'  => ($this->isInt($operand) || $this->isFloat($operand))
                        ? $operand
                        : null,
            '!'  => $this->isBool($operand) ? ScalarType::bool() : null,
            '~'  => $this->isInt($operand) ? ScalarType::int() : null,
            '++', '--' => ($this->isInt($operand) || $this->isFloat($operand))
                        ? $operand
                        : null,
            default => null,
        };
    }

    // =========================================================================
    //  Prédicats
    // =========================================================================

    private function isInt(Type $t): bool
    {
        return $t instanceof ScalarType && $t->name === ScalarType::INT;
    }

    private function isFloat(Type $t): bool
    {
        return $t instanceof ScalarType && $t->name === ScalarType::FLOAT;
    }

    private function isString(Type $t): bool
    {
        return $t instanceof ScalarType && $t->name === ScalarType::STRING;
    }

    private function isBool(Type $t): bool
    {
        return $t instanceof ScalarType && $t->name === ScalarType::BOOL;
    }

    private function isNumeric(Type $t): bool
    {
        return $this->isInt($t) || $this->isFloat($t);
    }

    private function areComparable(Type $left, Type $right): bool
    {
        if ($this->isNumeric($left) && $this->isNumeric($right)) {
            return true;
        }
        if ($this->isString($left) && $this->isString($right)) {
            return true;
        }
        return false;
    }

    private function areTypeCompatible(Type $left, Type $right): bool
    {
        // Compatible = l'un sous-type de l'autre (approximation sans SubtypeChecker)
        if ($left->equals($right)) {
            return true;
        }
        // int vs float compatibles
        if (($this->isInt($left) && $this->isFloat($right))
            || ($this->isFloat($left) && $this->isInt($right))) {
            return true;
        }
        // mixed compatible avec tout
        if ($left instanceof MixedType || $right instanceof MixedType) {
            return true;
        }
        return false;
    }
}