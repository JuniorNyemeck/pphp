<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;

/**
 * Contient toutes les déclarations top-level : classes, interfaces, fonctions.
 */
final class GlobalScope
{
        /** @var array<string, ClassInfo> */
    public array $classes = [];

    /**
     * Fonctions par nom. Chaque nom peut avoir plusieurs surcharges.
     *
     * @var array<string, FunctionInfo[]>
     */
    public array $functions = [];

    public function defineClass(ClassInfo $info): void
    {
        if (isset($this->classes[$info->name])) {
            $existing = $this->classes[$info->name];
            throw new TypeError(
                "Redéclaration de la classe '{$info->name}' "
                . "(déjà déclarée à la ligne {$existing->line})",
                null,
                $info->line,
                $info->column,
            );
        }
        $this->classes[$info->name] = $info;
    }

        public function defineFunction(FunctionInfo $info): void
    {
        $this->functions[$info->name][] = $info;
    }

    /**
     * Retourne toutes les surcharges d'une fonction, ou un tableau vide.
     *
     * @return FunctionInfo[]
     */
    public function getFunctions(string $name): array
    {
        return $this->functions[$name] ?? [];
    }

    /**
     * Retourne la première fonction trouvée (utile pour l'instant).
     */
    public function getFunction(string $name): ?FunctionInfo
    {
        $list = $this->functions[$name] ?? [];
        return $list[0] ?? null;
    }

    public function classExists(string $name): bool
    {
        return isset($this->classes[$name]);
    }

    public function getClass(string $name): ?ClassInfo
    {
        return $this->classes[$name] ?? null;
    }

    public function functionExists(string $name): bool
    {
        return isset($this->functions[$name]);
    }
  
}