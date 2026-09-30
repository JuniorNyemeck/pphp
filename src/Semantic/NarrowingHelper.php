<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Parser\Node\Expr\BinaryExpr;
use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\Expr\InstanceofExpr;
use PPhp\Parser\Node\Expr\LiteralExpr;
use PPhp\Parser\Node\Expr\UnaryExpr;
use PPhp\Parser\Node\Expr\VariableExpr;

/**
 * Calcule les narrowings (raffinements de type) à appliquer dans les blocs
 * conditionnels, à partir d'une expression de condition.
 *
 * Un narrowing est décrit par un nom de variable et une opération textuelle :
 *  - 'remove-null'      : retirer null du type
 *  - 'null'             : le type devient null
 *  - 'instanceof:Foo'   : le type devient Foo
 *
 * Le TypeChecker interprète ces opérations pour produire le type final.
 *
 * Supporte :
 *  - $x !== null / null !== $x
 *  - $x === null / null === $x
 *  - $x instanceof Foo
 *  - !(cond)             (inverse positif/négatif)
 *  - cond1 && cond2      (fusion des positifs)
 *  - cond1 || cond2      (fusion des négatifs)
 */
final class NarrowingHelper
{
    /**
     * Narrowings à appliquer si la condition est VRAIE.
     *
     * @return array<string, string>  varName => opération
     */
    public function positive(Expr $condition): array
    {
        // $x !== null
        if ($condition instanceof BinaryExpr && $condition->op === '!==') {
            $var = $this->varAgainstNull($condition->left, $condition->right);
            if ($var !== null) {
                return [$var => 'remove-null'];
            }
        }

        // $x === null
        if ($condition instanceof BinaryExpr && $condition->op === '===') {
            $var = $this->varAgainstNull($condition->left, $condition->right);
            if ($var !== null) {
                return [$var => 'null'];
            }
        }

        // $x instanceof Foo
        if ($condition instanceof InstanceofExpr && $condition->operand instanceof VariableExpr) {
            return [$condition->operand->name => 'instanceof:' . $condition->className];
        }

        // !(cond) → inverser
        if ($condition instanceof UnaryExpr && $condition->op === '!') {
            return $this->negative($condition->operand);
        }

        // cond1 && cond2 → fusion des positifs
        if ($condition instanceof BinaryExpr && $condition->op === '&&') {
            return $this->merge(
                $this->positive($condition->left),
                $this->positive($condition->right),
            );
        }

        return [];
    }

    /**
     * Narrowings à appliquer si la condition est FAUSSE.
     *
     * @return array<string, string>
     */
    public function negative(Expr $condition): array
    {
        // $x !== null → si faux, $x est null
        if ($condition instanceof BinaryExpr && $condition->op === '!==') {
            $var = $this->varAgainstNull($condition->left, $condition->right);
            if ($var !== null) {
                return [$var => 'null'];
            }
        }

        // $x === null → si faux, $x est non-nullable
        if ($condition instanceof BinaryExpr && $condition->op === '===') {
            $var = $this->varAgainstNull($condition->left, $condition->right);
            if ($var !== null) {
                return [$var => 'remove-null'];
            }
        }

        // !(cond) → inverser
        if ($condition instanceof UnaryExpr && $condition->op === '!') {
            return $this->positive($condition->operand);
        }

        // cond1 || cond2 → fusion des négatifs
        if ($condition instanceof BinaryExpr && $condition->op === '||') {
            return $this->merge(
                $this->negative($condition->left),
                $this->negative($condition->right),
            );
        }

        return [];
    }

    /**
     * Fusionne deux ensembles de narrowings.
     * En cas de conflit sur une même variable, on garde le premier
     * (le plus spécifique, car rencontré en premier dans la condition).
     *
     * @param array<string, string> $a
     * @param array<string, string> $b
     * @return array<string, string>
     */
    public function merge(array $a, array $b): array
    {
        return $a + $b;
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * Détecte les motifs `$var !== null`, `null !== $var`,
     * `$var === null`, `null === $var`.
     * Retourne le nom de la variable, ou null si ce n'est pas ce motif.
     */
    private function varAgainstNull(Expr $left, Expr $right): ?string
    {
        if ($left instanceof VariableExpr
            && $right instanceof LiteralExpr
            && $right->kind === 'null'
        ) {
            return $left->name;
        }
        if ($right instanceof VariableExpr
            && $left instanceof LiteralExpr
            && $left->kind === 'null'
        ) {
            return $right->name;
        }
        return null;
    }
}