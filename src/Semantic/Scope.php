<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;

/**
 * Table des symboles d'un scope. Le parent est consulté en cas
 * de lookup non résolu localement.
 */
final class Scope
{
    /** @var array<string, Symbol> */
    private array $symbols = [];

    public function __construct(
        public readonly ?Scope $parent = null,
        public readonly string $kind = 'block', // 'global', 'function', 'block'
    ) {}

    public function define(Symbol $symbol): void
    {
        if (isset($this->symbols[$symbol->name])) {
            $existing = $this->symbols[$symbol->name];
            throw new TypeError(
                "Redéclaration de '{$symbol->name}' dans le même scope "
                . "(déjà déclaré à la ligne {$existing->line})",
                null,
                $symbol->line,
                $symbol->column,
            );
        }
        $this->symbols[$symbol->name] = $symbol;
    }

    public function has(string $name): bool
    {
        return isset($this->symbols[$name]);
    }

    /**
     * Cherche dans ce scope uniquement (pas dans le parent).
     */
    public function lookupLocal(string $name): ?Symbol
    {
        return $this->symbols[$name] ?? null;
    }

    /**
     * Cherche dans ce scope puis remonte la chaîne des parents.
     */
    public function lookup(string $name): ?Symbol
    {
        if (isset($this->symbols[$name])) {
            return $this->symbols[$name];
        }
        return $this->parent?->lookup($name);
    }

    /**
     * @return array<string, Symbol>
     */
    public function all(): array
    {
        return $this->symbols;
    }

    /**
 * Redéfinit un symbole existant, sans lever d'erreur de redéclaration.
 * Utilisé par le narrowing pour remplacer temporairement un type.
 */
public function redefine(Symbol $symbol): void
{
    $this->symbols[$symbol->name] = $symbol;
}
}