<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;
use PPhp\Semantic\Type\ArrayType;
use PPhp\Semantic\Type\ClassType;
use PPhp\Semantic\Type\MixedType;
use PPhp\Semantic\Type\NullableType;
use PPhp\Semantic\Type\ScalarType;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\TypeFactory;
use PPhp\Semantic\Type\UnionType;

/**
 * Vérifie l'unicité et la disjonction des signatures de surcharge.
 */
final class SignatureChecker
{
    public function __construct(
        private readonly GlobalScope $globals,
        private readonly ClassHierarchy $hierarchy,
        private readonly SubtypeChecker $subtypes,
    ) {}

    /**
     * Vérifie une liste de surcharges pour un même nom.
     *
     * @param MethodInfo[]|FunctionInfo[] $overloads
     * @param array<int, class-string>     $paramTypeNodes  pas utilisé, on travaille sur les types résolus
     * @param string                       $context         pour les messages ("A::f" ou "f")
     */
    public function checkOverloads(array $overloads, string $context, string $file): void
    {
        // 1. Règle : même nombre de paramètres
        $paramCounts = array_map(
            fn($o) => count($o->params),
            $overloads,
        );
        if (count(array_unique($paramCounts)) > 1) {
            throw new TypeError(
                "Les surcharges de '{$context}' doivent avoir le même nombre de paramètres",
                $file,
                $overloads[0]->line,
                $overloads[0]->column,
            );
        }

        // 2. Règle : même visibilité et même staticité
        $this->checkModifiersUniform($overloads, $context, $file);

        // 3. Unicité : aucune paire n'a exactement les mêmes types
        // 4. Disjonction : aucune paire n'a un appel qui matche les deux
        $n = count($overloads);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $this->checkPair($overloads[$i], $overloads[$j], $context, $file);
            }
        }
    }

    /**
     * @param MethodInfo[]|FunctionInfo[] $overloads
     */
    private function checkModifiersUniform(array $overloads, string $context, string $file): void
    {
        if (empty($overloads) || !$overloads[0] instanceof MethodInfo) {
            return; // fonctions top-level : pas de visibilité
        }

        $refModifiers = $this->normalizedModifiers($overloads[0]);
        foreach ($overloads as $o) {
            $mods = $this->normalizedModifiers($o);
            if ($mods !== $refModifiers) {
                throw new TypeError(
                    "Toutes les surcharges de '{$context}' doivent avoir "
                    . "la même visibilité et la même staticité",
                    $file,
                    $o->line,
                    $o->column,
                );
            }
        }
    }

    /**
     * Normalise les modificateurs : ne garde que visibilité + static.
     *
     * @return string
     */
    private function normalizedModifiers(MethodInfo $m): string
    {
        $visibility = 'public';
        foreach (['public', 'protected', 'private'] as $v) {
            if (in_array($v, $m->modifiers, true)) {
                $visibility = $v;
                break;
            }
        }
        $static = in_array('static', $m->modifiers, true) ? 'static' : 'instance';
        return $visibility . ':' . $static;
    }

    private function checkPair(MethodInfo|FunctionInfo $a, MethodInfo|FunctionInfo $b, string $context, string $file): void
    {
        // Calculer les types résolus
        $aTypes = array_map(fn($p) => TypeFactory::fromNode($p->type), $a->params);
        $bTypes = array_map(fn($p) => TypeFactory::fromNode($p->type), $b->params);

        // Unicité : exactement les mêmes types
        $identical = true;
        for ($i = 0; $i < count($aTypes); $i++) {
            if (!$aTypes[$i]->equals($bTypes[$i])) {
                $identical = false;
                break;
            }
        }
        if ($identical) {
            throw new TypeError(
                "Les surcharges de '{$context}' ont une signature identique "
                . "(ligne {$b->line})",
                $file,
                $b->line,
                $b->column,
            );
        }

        // Disjonction : pour chaque position, les types doivent être disjoints
        for ($i = 0; $i < count($aTypes); $i++) {
            if (!$this->areDisjoint($aTypes[$i], $bTypes[$i])) {
                throw new TypeError(
                    "Les surcharges de '{$context}' ne sont pas disjointes : "
                    . "paramètre " . ($i + 1) . ", "
                    . "'{$aTypes[$i]}' et '{$bTypes[$i]}' peuvent recevoir la même valeur",
                    $file,
                    $b->line,
                    $b->column,
                );
            }
        }
    }

    /**
     * Deux types sont disjoints si aucun type concret n'est sous-type des deux.
     * Approximation : on regarde les sous-types évidents.
     */
    private function areDisjoint(Type $a, Type $b): bool
    {
        // mixed n'est disjoint de rien
        if ($a instanceof MixedType || $b instanceof MixedType) {
            return false;
        }

        // Si l'un est sous-type de l'autre, ils ne sont pas disjoints
        if ($this->subtypes->isSubtypeOf($a, $b)) {
            return false;
        }
        if ($this->subtypes->isSubtypeOf($b, $a)) {
            return false;
        }

        // Nullable : ?T et U sont disjoints si T et U le sont, ET si U n'est pas null
        if ($a instanceof NullableType) {
            // Un type non-nullable peut toujours matcher via sa partie non-null
            // ?T et U sont disjoints si T et U sont disjoints et U ≠ null
            $aInner = $a->inner;
            return $this->areDisjoint($aInner, $b);
        }
        if ($b instanceof NullableType) {
            return $this->areDisjoint($a, $b->inner);
        }

        // Union : A|B et C sont disjoints si A et C sont disjoints ET B et C sont disjoints
        if ($a instanceof UnionType) {
            foreach ($a->members as $m) {
                if (!$this->areDisjoint($m, $b)) {
                    return false;
                }
            }
            return true;
        }
        if ($b instanceof UnionType) {
            foreach ($b->members as $m) {
                if (!$this->areDisjoint($a, $m)) {
                    return false;
                }
            }
            return true;
        }

        // Tableaux : T[] et U[] sont disjoints si T et U sont disjoints
        if ($a instanceof ArrayType && $b instanceof ArrayType) {
            return $this->areDisjoint($a->element, $b->element);
        }

        // Scalaires : int et float ne sont pas disjoints (int <: float)
        // Déjà couvert par la vérification de sous-type au-dessus.
        // int et string sont disjoints.
        if ($a instanceof ScalarType && $b instanceof ScalarType) {
            return true; // si on arrive ici, aucun n'est sous-type de l'autre
        }

        // Classes : Dog et Cat sont disjoints si aucun n'est sous-type de l'autre
        // ET qu'ils n'ont pas de descendants communs.
        // Approximation : si aucun n'est sous-type de l'autre, on les considère disjoints.
        if ($a instanceof ClassType && $b instanceof ClassType) {
            return true; // si on arrive ici, aucun n'est sous-type de l'autre
        }

        // Par défaut, on considère disjoint (les cas non couverts sont rares)
        return true;
    }
}