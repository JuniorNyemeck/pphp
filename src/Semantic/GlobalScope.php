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

    /** @var array<string, FunctionInfo> */
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
        $sig = $info->signature();
        // En 3a on interdit toute redéclaration de fonction, même avec des signatures différentes.
        // (La surcharge viendra à l'étape 5.)
        foreach ($this->functions as $existing) {
            if ($existing->name === $info->name) {
                throw new TypeError(
                    "Redéclaration de la fonction '{$info->name}' "
                    . "(déjà déclarée à la ligne {$existing->line})",
                    null,
                    $info->line,
                    $info->column,
                );
            }
        }
        $this->functions[$info->name] = $info;
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

    public function getFunction(string $name): ?FunctionInfo
    {
        return $this->functions[$name] ?? null;
    }
}