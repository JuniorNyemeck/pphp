<?php

declare(strict_types=1);

namespace PPhp\Parser\Node;

/**
 * Un type syntaxique.
 *
 * Exemples : int, float, string, bool, T[], ?T, A|B, \Foo\Bar, self, parent
 */
final class TypeNode extends NodeBase
{
    /**
     * @param string     $name      Nom du type de base (int, float, ..., ou nom de classe)
     * @param bool       $nullable  ?T
     * @param int        $arrayDepth  Nombre de []  (0 = pas un tableau, 1 = T[], 2 = T[][])
     * @param TypeNode[] $union     Types d'une union A|B (le type de base est le premier)
     * @param TypeNode[] $typeArgs  Arguments génériques Box<int> (vide en 2b)
     */
    public function __construct(
        int $line,
        int $column,
        public readonly string $name,
        public readonly bool $nullable = false,
        public readonly int $arrayDepth = 0,
        public readonly array $union = [],
        public readonly array $typeArgs = [],
    ) {
        parent::__construct($line, $column);
    }

    public function isScalar(): bool
    {
        return in_array(strtolower($this->name), ['int', 'float', 'bool', 'string'], true);
    }

    public function isBuiltin(): bool
    {
        return in_array(strtolower($this->name), [
            'int', 'float', 'bool', 'string', 'array', 'object', 'mixed',
            'void', 'never', 'null', 'callable', 'iterable', 'self', 'parent',
        ], true);
    }

    public function isArray(): bool
    {
        return $this->arrayDepth > 0;
    }

    public function isUnion(): bool
    {
        return count($this->union) > 0;
    }
}