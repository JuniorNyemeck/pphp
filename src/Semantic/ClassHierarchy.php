<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;

/**
 * Gère la hiérarchie des classes et interfaces :
 *  - résolution du parent
 *  - résolution des interfaces (directes et transitives)
 *  - détection des cycles
 *  - vérification de la cohérence
 */
final class ClassHierarchy
{
    /** @var array<string, string[]> Cache : nom de classe → liste des ancêtres (parents + interfaces, transitifs) */
    private array $ancestorsCache = [];

    /** @var array<string, true> Classes en cours de résolution (pour détection de cycle) */
    private array $resolving = [];

    public function __construct(private readonly GlobalScope $globals) {}

    /**
     * Vérifie la cohérence globale de la hiérarchie.
     * À appeler une fois après la collecte.
     */
    public function validate(string $file): void
{
    foreach ($this->globals->classes as $class) {
        $this->validateClass($class, $file);
    }

    // Détection de cycles
    foreach ($this->globals->classes as $class) {
        $this->detectCycle($class->name, $file);
    }

    // Vérification des méthodes abstraites et interfaces non implémentées
    foreach ($this->globals->classes as $class) {
        $this->checkAbstractMethodsImplemented($class, $file);
    }

    // Vérification des overrides
    $subtypes = new SubtypeChecker($this->globals, $this);
    $overrideChecker = new OverrideChecker($this->globals, $this, $subtypes);
    foreach ($this->globals->classes as $class) {
        $overrideChecker->check($class, $file);
    }
    
}


   private function validateClass(ClassInfo $class, string $file): void
{
    // 1. Le parent (extends) existe et est du bon genre
    if ($class->parent !== null) {
        $parent = $this->globals->getClass($class->parent);
        if ($parent === null) {
            $kind = $class->isInterface ? "Interface parente" : "Classe parente";
            throw new TypeError(
                "{$kind} '{$class->parent}' inconnue (pour '{$class->name}')",
                $file,
                $class->line,
                $class->column,
            );
        }

        if ($class->isInterface) {
            if (!$parent->isInterface) {
                throw new TypeError(
                    "Une interface ne peut étendre qu'une interface : "
                    . "'{$class->name}' extends '{$class->parent}' (qui est une classe)",
                    $file,
                    $class->line,
                    $class->column,
                );
            }
        } else {
            if ($parent->isInterface) {
                throw new TypeError(
                    "'{$class->name}' ne peut pas étendre l'interface '{$class->parent}' "
                    . "(utilisez 'implements')",
                    $file,
                    $class->line,
                    $class->column,
                );
            }

            // Une classe final ne peut pas être étendue
            if (in_array('final', $parent->modifiers, true)) {
                throw new TypeError(
                    "Impossible d'étendre la classe finale '{$class->parent}'",
                    $file,
                    $class->line,
                    $class->column,
                );
            }
        }
    }

    // 2. Une classe ne peut pas être à la fois abstract et final
    if (!$class->isInterface
        && in_array('abstract', $class->modifiers, true)
        && in_array('final', $class->modifiers, true)
    ) {
        throw new TypeError(
            "La classe '{$class->name}' ne peut pas être à la fois 'abstract' et 'final'",
            $file,
            $class->line,
            $class->column,
        );
    }

    // 3. Les interfaces implémentées existent et sont bien des interfaces
    foreach ($class->implements as $iface) {
        $i = $this->globals->getClass($iface);
        if ($i === null) {
            throw new TypeError(
                "Interface '{$iface}' inconnue (implémentée par '{$class->name}')",
                $file,
                $class->line,
                $class->column,
            );
        }
        if (!$i->isInterface) {
            throw new TypeError(
                "'{$iface}' n'est pas une interface ('{$class->name}' l'implémente)",
                $file,
                $class->line,
                $class->column,
            );
        }
    }

    // 4. Une interface ne peut pas avoir de propriétés
    if ($class->isInterface && !empty($class->properties)) {
        $firstProp = reset($class->properties);
        throw new TypeError(
            "Une interface ne peut pas déclarer de propriétés "
            . "('{$class->name}::\${$firstProp->name}')",
            $file,
            $firstProp->line,
            $firstProp->column,
        );
    }

    // 5. Une méthode abstract doit être dans une classe abstract ou une interface
    foreach ($class->methods as $overloads) {
    foreach ($overloads as $method) {
        if ($method->isAbstract()
            && !$class->isInterface
            && !in_array('abstract', $class->modifiers, true)
        ) {
            throw new TypeError(
                "La méthode abstract '{$class->name}::{$method->name}' "
                . "doit être dans une classe abstract ou une interface",
                $file,
                $method->line,
                $method->column,
            );
        }

        // Une méthode ne peut pas être à la fois abstract et final
        if (in_array('abstract', $method->modifiers, true)
            && in_array('final', $method->modifiers, true)
        ) {
            throw new TypeError(
                "La méthode '{$class->name}::{$method->name}' "
                . "ne peut pas être à la fois 'abstract' et 'final'",
                $file,
                $method->line,
                $method->column,
            );
        }

        // Une méthode abstract ne peut pas avoir de corps $subtypes
        if (in_array('abstract', $method->modifiers, true)
            && $method->ast !== null
            && $method->ast->body !== null
        ) {
            throw new TypeError(
                "La méthode abstract '{$class->name}::{$method->name}' "
                . "ne peut pas avoir de corps",
                $file,
                $method->line,
                $method->column,
            );
        }
    }
}
}



/**
 * Vérifie qu'une classe concrète implémente toutes ses méthodes abstraites
 * (héritées ou déclarées) et toutes celles des interfaces qu'elle implémente.
 */
private function checkAbstractMethodsImplemented(ClassInfo $class, string $file): void
{
    // Une classe abstraite ou une interface n'a pas à tout implémenter.
    if ($class->isInterface) {
        return;
    }
    if (in_array('abstract', $class->modifiers, true)) {
        return;
    }

    // 1. Méthodes abstraites héritées (des parents)
    $missing = $this->findUnimplementedAbstractMethods($class);

    if (!empty($missing)) {
        $names = implode(', ', array_map(fn($m) => $m->declaringClass . '::' . $m->name, $missing));
        throw new TypeError(
            "La classe concrète '{$class->name}' doit implémenter les méthodes abstraites : {$names}",
            $file,
            $class->line,
            $class->column,
        );
    }

    // 2. Méthodes déclarées par les interfaces implémentées
    $missingFromInterfaces = $this->findUnimplementedInterfaceMethods($class);
    if (!empty($missingFromInterfaces)) {
        $names = implode(', ', array_map(fn($m) => $m['iface'] . '::' . $m['method'], $missingFromInterfaces));
        throw new TypeError(
            "La classe '{$class->name}' doit implémenter les méthodes d'interface : {$names}",
            $file,
            $class->line,
            $class->column,
        );
    }
}

/**
 * @return MethodInfo[]
 */
private function findUnimplementedAbstractMethods(ClassInfo $class): array
{
    $missing = [];
    $ancestors = $this->ancestorsOf($class->name);

    foreach ($ancestors as $ancestorName) {
        $ancestor = $this->globals->getClass($ancestorName);
        if ($ancestor === null) {
            continue;
        }
        // On saute les interfaces : elles sont traitées séparément
        if ($ancestor->isInterface) {
            continue;
        }
        foreach ($ancestor->methods as $overloads) {
        foreach ($overloads as $method) {
                if (!$method->isAbstract()) {
                    continue;
                }
                if ($this->isMethodImplementedIn($class->name, $method->name, $ancestorName)) {
                    continue;
                }
                $missing[$method->name] = $method;
            }
            
        }
    }
    return array_values($missing);
}

/**
 * Vérifie si $methodName est implémentée (non-abstract) dans la chaîne
 * qui va de $className jusqu'à (mais excluant) $stopAt.
 */
private function isMethodImplementedIn(string $className, string $methodName, string $stopAt): bool
{
    $current = $this->globals->getClass($className);
    while ($current !== null && $current->name !== $stopAt) {
        if (isset($current->methods[$methodName])) {
            foreach ($current->methods[$methodName] as $overload) {
                if (!$overload->isAbstract()) {
                    return true;
                }
            }
        }
        $current = $current->parent !== null
            ? $this->globals->getClass($current->parent)
            : null;
    }
    return false;
}

/**
 * @return array<array{iface: string, method: string}>
 */
private function findUnimplementedInterfaceMethods(ClassInfo $class): array
{
    $missing = [];
    $ancestors = $this->ancestorsOf($class->name);

    foreach ($ancestors as $ancestorName) {
        $ancestor = $this->globals->getClass($ancestorName);
        if ($ancestor === null || !$ancestor->isInterface) {
            continue;
        }
        foreach ($ancestor->methods as $overloads) {
        foreach ($overloads as $method) {
                // La méthode est-elle implémentée dans $class ou ses parents ?
                if ($this->isMethodImplementedIn($class->name, $method->name, $ancestorName)) {
                    continue;
                }
                $missing[] = ['iface' => $ancestorName, 'method' => $method->name];
            }
        }
    }
    return $missing;
}

    private function detectCycle(string $name, string $file): void
    {
        $this->resolving = [];
        $this->walkForCycle($name, $file);
    }

    private function walkForCycle(string $name, string $file): void
    {
        if (isset($this->resolving[$name])) {
            throw new TypeError(
                "Cycle d'héritage détecté autour de '{$name}'",
                $file,
            );
        }
        $this->resolving[$name] = true;

        $class = $this->globals->getClass($name);
        if ($class !== null) {
            if ($class->parent !== null) {
                $this->walkForCycle($class->parent, $file);
            }
            foreach ($class->implements as $iface) {
                $this->walkForCycle($iface, $file);
            }
        }

        unset($this->resolving[$name]);
    }

    /**
     * Renvoie tous les ancêtres (parents + interfaces, transitifs) d'une classe.
     *
     * @return string[]
     */
    public function ancestorsOf(string $className): array
    {
        if (isset($this->ancestorsCache[$className])) {
            return $this->ancestorsCache[$className];
        }

        $result = [];
        $class = $this->globals->getClass($className);
        if ($class === null) {
            return [];
        }

        // Parent
        if ($class->parent !== null) {
            $result[] = $class->parent;
            $result = array_merge($result, $this->ancestorsOf($class->parent));
        }

        // Interfaces
        foreach ($class->implements as $iface) {
            $result[] = $iface;
            $result = array_merge($result, $this->ancestorsOf($iface));
        }

        // Pour une interface, ses parents (extends)
        if ($class->isInterface && $class->parent !== null) {
            // (déjà géré plus haut via $class->parent)
        }

        $result = array_values(array_unique($result));
        return $this->ancestorsCache[$className] = $result;
    }

    /**
     * Vérifie si $sub est un ancêtre (parent direct ou indirect, interface) de $sup.
     * $sub <: $sup en termes de classes.
     */
    public function isSubclassOf(string $sub, string $sup): bool
    {
        if ($sub === $sup) {
            return true;
        }
        return in_array($sup, $this->ancestorsOf($sub), true);
    }

    /**
     * Vérifie si $name implémente l'interface $iface.
     */
    public function implementsInterface(string $name, string $iface): bool
    {
        return in_array($iface, $this->ancestorsOf($name), true);
    }
}