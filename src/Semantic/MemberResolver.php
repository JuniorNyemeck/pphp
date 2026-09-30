<?php

declare(strict_types=1);

namespace PPhp\Semantic;

/**
 * Résout les membres (propriétés et méthodes) dans une hiérarchie de classes.
 * Gère la visibilité (public, protected, private).
 */
final class MemberResolver
{
    public function __construct(
        private readonly GlobalScope $globals,
        private readonly ClassHierarchy $hierarchy,
        private readonly SubtypeChecker $subtypes,
    ) {}

    /**
     * Trouve une propriété dans la classe $className ou ses ancêtres.
     * Retourne null si introuvable.
     */
    public function findProperty(string $className, string $propName): ?PropertyInfo
    {
        $class = $this->globals->getClass($className);
        if ($class === null) {
            return null;
        }
        if (isset($class->properties[$propName])) {
            return $class->properties[$propName];
        }
        if ($class->parent !== null) {
            return $this->findProperty($class->parent, $propName);
        }
        return null;
    }

    /**
     * Trouve une méthode dans la classe $className ou ses ancêtres.
     * Retourne null si introuvable.
     */
    public function findMethod(string $className, string $methodName): ?MethodInfo
{
    $overloads = $this->findMethodOverloads($className, $methodName);
    return $overloads[0] ?? null;
}

/**
 * @return MethodInfo[]
 */
/**
 * Retourne toutes les surcharges disponibles pour une méthode,
 * en fusionnant la classe et ses ancêtres.
 *
 * Si la classe enfant redéfinit une signature du parent, la version
 * enfant écrase la version parent pour cette signature.
 *
 * @return MethodInfo[]
 */
/**
 * Retourne toutes les surcharges effectives pour une méthode dans une classe,
 * en tenant compte de l'override par matchabilité (contravariance).
 *
 * Règle : si une surcharge enfant a des paramètres qui acceptent (par sous-typage)
 * TOUS les paramètres d'une surcharge parent (même nom, même nombre), elle
 * override la parent. La surcharge parent est retirée de la liste effective.
 *
 * @return MethodInfo[]
 */
public function findMethodOverloads(string $className, string $methodName): array
{
    $class = $this->globals->getClass($className);
    if ($class === null) {
        return [];
    }

    $own = $class->methods[$methodName] ?? [];
    $inherited = $class->parent !== null
        ? $this->findMethodOverloads($class->parent, $methodName)
        : [];

    if (empty($own)) {
        return $inherited;
    }
    if (empty($inherited)) {
        return $own;
    }

    // Fusion : pour chaque surcharge propre, retirer les surcharges héritées
    // qu'elle override (matchabilité paramètres), puis l'ajouter.
    $result = $inherited;
    foreach ($own as $ownOverload) {
        // Retirer les surcharges héritées qui sont overridées par $ownOverload
        $result = array_values(array_filter(
            $result,
            fn(MethodInfo $inheritedOverload) => !$this->overrides(
                $ownOverload,
                $inheritedOverload,
            ),
        ));
        // Ajouter la surcharge propre
        $result[] = $ownOverload;
    }

    return $result;
}

/**
 * Vérifie si $child override $parent au sens de la contravariance :
 * pour chaque paramètre, le type enfant doit accepter (par sous-typage)
 * le type parent. Même nombre de paramètres requis.
 */
private function overrides(MethodInfo $child, MethodInfo $parent): bool
{
    if (count($child->params) !== count($parent->params)) {
        return false;
    }

    foreach ($child->params as $i => $childParam) {
        $childType = \PPhp\Semantic\Type\TypeFactory::fromNode(
            $childParam->type,
            $child->declaringClass,
        );
        $parentType = \PPhp\Semantic\Type\TypeFactory::fromNode(
            $parent->params[$i]->type,
            $parent->declaringClass,
        );

        // Contravariance : le type parent doit être sous-type du type enfant.
        if (!$this->subtypes->isSubtypeOf($parentType, $childType)) {
            return false;
        }
    }

    return true;
}

/**
 * Signature normalisée : types de paramètres dans l'ordre.
 */
private function signatureKey(MethodInfo|FunctionInfo $overload): string
{
    $types = array_map(
        fn($p) => (string) \PPhp\Semantic\Type\TypeFactory::fromNode($p->type),
        $overload->params,
    );
    return implode(',', $types);
}
    /**
     * @return MethodInfo[]
     */
   
   
/*     public function findMethodOverloads(string $className, string $methodName): array
    {
        $class = $this->globals->getClass($className);
        if ($class === null) {
            return [];
        }
        if (isset($class->methods[$methodName])) {
            return $class->methods[$methodName];
        }
        if ($class->parent !== null) {
            return $this->findMethodOverloads($class->parent, $methodName);
        }
        return [];
    } */

    /**
     * Vérifie si une propriété est accessible depuis un contexte donné.
     *
     * - public    : toujours
     * - protected : depuis la classe déclarante ou ses sous-classes
     * - private   : uniquement depuis la classe déclarante
     */
    public function canAccessProperty(
        PropertyInfo $prop,
        ?ClassInfo $contextClass,
    ): bool {
        if ($prop->isPublic()) {
            return true;
        }
        if ($contextClass === null) {
            return false;
        }

        if (in_array('private', $prop->modifiers, true)) {
            return $contextClass->name === $prop->declaringClass;
        }

        if (in_array('protected', $prop->modifiers, true)) {
            return $contextClass->name === $prop->declaringClass
                || $this->hierarchy->isSubclassOf($contextClass->name, $prop->declaringClass);
        }

        return false;
    }

    /**
     * Vérifie si une méthode est accessible depuis un contexte donné.
     *
     * - public    : toujours
     * - protected : depuis la classe déclarante ou ses sous-classes
     * - private   : uniquement depuis la classe déclarante
     */
    public function canAccessMethod(
        MethodInfo $method,
        ?ClassInfo $contextClass,
    ): bool {
        if ($method->isPublic()) {
            return true;
        }
        if ($contextClass === null) {
            return false;
        }

        if (in_array('private', $method->modifiers, true)) {
            return $contextClass->name === $method->declaringClass;
        }

        if (in_array('protected', $method->modifiers, true)) {
            return $contextClass->name === $method->declaringClass
                || $this->hierarchy->isSubclassOf($contextClass->name, $method->declaringClass);
        }

        return false;
    }
}