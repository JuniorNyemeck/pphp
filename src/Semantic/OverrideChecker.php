<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\TypeFactory;

/**
 * Vérifie la cohérence des overrides :
 *  - contravariance des paramètres
 *  - covariance du retour
 *  - visibilité
 *  - static
 *  - final
 *  - covariance des propriétés
 */
final class OverrideChecker
{
    public function __construct(
        private readonly GlobalScope $globals,
        private readonly ClassHierarchy $hierarchy,
        private readonly SubtypeChecker $subtypes,
    ) {}

    /**
     * Vérifie tous les overrides d'une classe par rapport à son parent.
     */
    public function check(ClassInfo $class, string $file): void
{
    // 1. Vérifier l'override par rapport au parent (extends)
    if ($class->parent !== null) {
        $parent = $this->globals->getClass($class->parent);
        if ($parent !== null) {
            $this->checkMethods($class, $parent, $file);
            $this->checkProperties($class, $parent, $file);
        }
    }

    // 2. Vérifier l'implémentation des interfaces
    foreach ($class->implements as $ifaceName) {
        $iface = $this->globals->getClass($ifaceName);
        if ($iface === null || !$iface->isInterface) {
            continue;
        }
        $this->checkMethods($class, $iface, $file);
        // Pas de propriétés dans les interfaces (déjà interdit)
    }
}

    // =========================================================================
    //  Méthodes
    // =========================================================================

    private function checkMethods(ClassInfo $class, ClassInfo $parent, string $file): void
{
    foreach ($class->methods as $name => $childOverloads) {
    // Chercher une méthode parent portant le même nom
    $parentAny = $this->findInheritedMethod($parent, $name);
    if ($parentAny !== null) {
        $parentCount = count($parentAny->params);
        $childCount = count($childOverloads[0]->params);
        if ($parentCount !== $childCount) {
            throw new TypeError(
                "La méthode '{$class->name}::{$name}' a {$childCount} paramètre(s), "
                . "mais '{$parentAny->declaringClass}::{$name}' en a {$parentCount}. "
                . "Toutes les surcharges d'un même nom doivent avoir le même nombre de paramètres.",
                $file,
                $childOverloads[0]->line,
                $childOverloads[0]->column,
            );
        }
    }

    foreach ($childOverloads as $childMethod) {
            // Chercher la méthode parent de même signature
            $parentMethod = $this->findInheritedMethodToOverride($parent, $name, $childMethod);

            // Pas trouvée : c'est une nouvelle surcharge, pas un override
            if ($parentMethod === null) {
                continue;
            }

            // Une méthode private dans le parent n'est pas overridée :
            // c'est une nouvelle méthode.
            if (in_array('private', $parentMethod->modifiers, true)) {
                continue;
            }

            // Le parent ne doit pas être final
            if (in_array('final', $parentMethod->modifiers, true)) {
                throw new TypeError(
                    "Impossible d'overrider la méthode finale "
                    . "'{$parentMethod->declaringClass}::{$name}' dans '{$class->name}'",
                    $file,
                    $childMethod->line,
                    $childMethod->column,
                );
            }

            // Static cohérent
            $parentStatic = in_array('static', $parentMethod->modifiers, true);
            $childStatic = in_array('static', $childMethod->modifiers, true);
            if ($parentStatic !== $childStatic) {
                $kind = $parentStatic ? 'static' : 'non-static';
                throw new TypeError(
                    "La méthode '{$class->name}::{$name}' doit être {$kind} "
                    . "pour overrider '{$parentMethod->declaringClass}::{$name}'",
                    $file,
                    $childMethod->line,
                    $childMethod->column,
                );
            }

            // Visibilité : pas de réduction
            $this->checkVisibility(
                $parentMethod->modifiers,
                $childMethod->modifiers,
                "méthode '{$class->name}::{$name}'",
                $file,
                $childMethod->line,
                $childMethod->column,
            );

            // Nombre de paramètres
            $parentParamCount = count($parentMethod->params);
            $childParamCount = count($childMethod->params);
            if ($parentParamCount !== $childParamCount) {
                throw new TypeError(
                    "La méthode '{$class->name}::{$name}' override "
                    . "'{$parentMethod->declaringClass}::{$name}' : "
                    . "attend {$parentParamCount} paramètre(s), "
                    . "mais en a {$childParamCount}",
                    $file,
                    $childMethod->line,
                    $childMethod->column,
                );
            }

            // Contravariance des paramètres
            $ownerParent = $this->findDeclaringClass($parent->name, $name);
            $ownerChild = $this->findDeclaringClass($class->name, $name);

            foreach ($childMethod->params as $i => $childParam) {
                $parentParam = $parentMethod->params[$i];

                $parentType = TypeFactory::fromNode(
                    $parentParam->type,
                    $ownerParent?->name,
                    $ownerParent?->parent,
                );
                $childType = TypeFactory::fromNode(
                    $childParam->type,
                    $ownerChild?->name,
                    $ownerChild?->parent,
                );

                // Contravariance : le type enfant doit être SUPER-TYPE du parent.
                // C'est-à-dire : parentType <: childType.
                if (!$this->subtypes->isSubtypeOf($parentType, $childType)) {
                    throw new TypeError(
                        "Paramètre " . ($i + 1) . " de '{$class->name}::{$name}' : "
                        . "le type '{$childType}' n'est pas un super-type de "
                        . "'{$parentType}' (contravariance violée)",
                        $file,
                        $childParam->line(),
                        $childParam->column(),
                    );
                }
            }

            // Covariance du retour
            $parentReturn = $parentMethod->returnType;
            $childReturn = $childMethod->returnType;

            // null = constructeur ou destructeur, pas de vérification de retour
            if ($parentReturn !== null && $childReturn !== null) {
                if (!$this->subtypes->isSubtypeOf($childReturn, $parentReturn)) {
                    throw new TypeError(
                        "Type de retour de '{$class->name}::{$name}' : "
                        . "'{$childReturn}' n'est pas un sous-type de '{$parentReturn}' "
                        . "(covariance violée)",
                        $file,
                        $childMethod->line,
                        $childMethod->column,
                    );
                }
            }

            // abstract : un parent concret ne peut pas devenir abstract
            $parentAbstract = in_array('abstract', $parentMethod->modifiers, true)
                || $parentMethod->ast?->body === null;
            $childAbstract = in_array('abstract', $childMethod->modifiers, true)
                || $childMethod->ast?->body === null;

            if (!$parentAbstract && $childAbstract) {
                throw new TypeError(
                    "La méthode '{$class->name}::{$name}' ne peut pas devenir abstract : "
                    . "'{$parentMethod->declaringClass}::{$name}' est concrète",
                    $file,
                    $childMethod->line,
                    $childMethod->column,
                );
            }
        }
    }
}
    // =========================================================================
    //  Propriétés
    // =========================================================================

    private function checkProperties(ClassInfo $class, ClassInfo $parent, string $file): void
    {
        foreach ($class->properties as $name => $childProp) {
            $parentProp = $this->findInheritedProperty($parent, $name);
            if ($parentProp === null) {
                continue;
            }

            // Une propriété private dans le parent n'est pas overridée
            if (in_array('private', $parentProp->modifiers, true)) {
                continue;
            }

            // Visibilité
            $this->checkVisibility(
                $parentProp->modifiers,
                $childProp->modifiers,
                "propriété '{$class->name}::\${$name}'",
                $file,
                $childProp->line,
                $childProp->column,
            );

            // Covariance du type
            $ownerParent = $this->findDeclaringClass($parent->name, $name); // pas pertinent pour prop
            // On utilise les declaringClass enregistrés dans les PropertyInfo
            $parentType = $parentProp->type;
            $childType = $childProp->type;

            if (!$this->subtypes->isSubtypeOf($childType, $parentType)) {
                throw new TypeError(
                    "Type de la propriété '{$class->name}::\${$name}' : "
                    . "'{$childType}' n'est pas un sous-type de '{$parentType}' "
                    . "(covariance violée)",
                    $file,
                    $childProp->line,
                    $childProp->column,
                );
            }
        }
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

/**
 * Cherche une méthode héritée de même signature que $childMethod.
 */

private function findInheritedMethod(ClassInfo $class, string $methodName): ?MethodInfo
{
    $current = $class;
    while ($current !== null) {
        if (isset($current->methods[$methodName])) {
            return $current->methods[$methodName][0] ?? null;
        }
        $current = $current->parent !== null
            ? $this->globals->getClass($current->parent)
            : null;
    }
    return null;
}

/**
 * Cherche une méthode héritée que $childMethod tente d'overrider.
 *
 * Un override se reconnaît par la contravariance : les paramètres de l'enfant
 * doivent accepter les paramètres du parent (par sous-typage). Si une méthode
 * parent est trouvée où l'enfant est PLUS STRICT, c'est une violation de
 * contravariance (à signaler).
 *
 * Renvoie la méthode parent trouvée, ou null.
 */
private function findInheritedMethodToOverride(
    ClassInfo $class,
    string $methodName,
    MethodInfo $childMethod,
): ?MethodInfo {
    $current = $class;
    while ($current !== null) {
        if (isset($current->methods[$methodName])) {
            foreach ($current->methods[$methodName] as $candidate) {
                if ($this->signaturesAreRelated($childMethod, $candidate)) {
                    return $candidate;
                }
            }
        }
        $current = $current->parent !== null
            ? $this->globals->getClass($current->parent)
            : null;
    }
    return null;
}

/**
 * Deux signatures sont "reliées" si elles ont le même nombre de paramètres
 * ET que pour chaque position, les types sont en relation (sous-type dans
 * un sens ou l'autre). Les méthodes sans paramètres sont reliées.
 */
private function signaturesAreRelated(MethodInfo $a, MethodInfo $b): bool
{
    if (count($a->params) !== count($b->params)) {
        return false;
    }

    foreach ($a->params as $i => $paramA) {
        $typeA = TypeFactory::fromNode($paramA->type, $a->declaringClass);
        $typeB = TypeFactory::fromNode($b->params[$i]->type, $b->declaringClass);

        if (!$this->subtypes->isSubtypeOf($typeA, $typeB)
            && !$this->subtypes->isSubtypeOf($typeB, $typeA)
        ) {
            return false;
        }
    }
    return true;
}

/**
 * Signature normalisée : types de paramètres dans l'ordre.
 */
private function signatureKey(MethodInfo $method): string
{
    $types = array_map(
        fn($p) => (string) TypeFactory::fromNode($p->type),
        $method->params,
    );
    return implode(',', $types);
}

    private function findInheritedProperty(ClassInfo $class, string $propName): ?PropertyInfo
    {
        $current = $class;
        while ($current !== null) {
            if (isset($current->properties[$propName])) {
                return $current->properties[$propName];
            }
            $current = $current->parent !== null
                ? $this->globals->getClass($current->parent)
                : null;
        }
        return null;
    }

    private function findDeclaringClass(string $className, string $memberName): ?ClassInfo
    {
        $current = $this->globals->getClass($className);
        while ($current !== null) {
            if (isset($current->methods[$memberName]) || isset($current->properties[$memberName])) {
                return $current;
            }
            $current = $current->parent !== null
                ? $this->globals->getClass($current->parent)
                : null;
        }
        return null;
    }

    /**
     * Vérifie que la visibilité enfant n'est pas plus restrictive que la visibilité parent.
     *
     * @param string[] $parentModifiers
     * @param string[] $childModifiers
     */
    private function checkVisibility(
        array $parentModifiers,
        array $childModifiers,
        string $context,
        string $file,
        int $line,
        int $column,
    ): void {
        $rank = static fn(array $mods): int => match (true) {
            in_array('public', $mods, true)    => 3,
            in_array('protected', $mods, true) => 2,
            in_array('private', $mods, true)   => 1,
            default                            => 3, // défaut = public
        };

        $parentRank = $rank($parentModifiers);
        $childRank = $rank($childModifiers);

        if ($childRank < $parentRank) {
            throw new TypeError(
                "Visibilité réduite pour la {$context} : "
                . "le parent est " . $this->describeVisibility($parentRank)
                . ", l'enfant est " . $this->describeVisibility($childRank),
                $file,
                $line,
                $column,
            );
        }
    }

    private function describeVisibility(int $rank): string
    {
        return match ($rank) {
            3 => 'public',
            2 => 'protected',
            1 => 'private',
            default => '?',
        };
    }
}