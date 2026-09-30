<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\TypeFactory;

/**
 * Résout la bonne surcharge d'une méthode ou fonction en fonction
 * des types d'arguments.
 *
 * Toutes les surcharges d'un même nom ont le même nombre de paramètres
 * (garanti par SignatureChecker en 5a).
 */
final class OverloadResolver
{
    public function __construct(
        private readonly SubtypeChecker $subtypes,
    ) {}

    /**
     * Résout la surcharge correspondant aux types d'arguments.
     *
     * @param MethodInfo[]|FunctionInfo[] $overloads
     * @param Type[]                       $argTypes
     */
    public function resolve(
        array $overloads,
        array $argTypes,
        string $context,
        string $file,
        int $line,
        int $column,
    ): MethodInfo|FunctionInfo {
        if (empty($overloads)) {
            throw new TypeError(
                "Aucune méthode ou fonction nommée '{$context}'",
                $file,
                $line,
                $column,
            );
        }

        // 1. Vérifier le nombre d'arguments
        $expected = count($overloads[0]->params);
        $actual = count($argTypes);
        if ($expected !== $actual) {
            throw new TypeError(
                "'{$context}' attend {$expected} argument(s), mais en reçoit {$actual}",
                $file,
                $line,
                $column,
            );
        }

        // 2. Filtrer les candidates
        $candidates = [];
        foreach ($overloads as $overload) {
            if ($this->accepts($overload, $argTypes)) {
                $candidates[] = $overload;
            }
        }

        // 3. Aucune candidate
        if (empty($candidates)) {
            throw new TypeError(
                $this->noMatchMessage($overloads, $argTypes, $context),
                $file,
                $line,
                $column,
            );
        }

        // 4. Plusieurs candidates (ne devrait pas arriver à cause de la disjonction)
        if (count($candidates) > 1) {
            throw new TypeError(
                "Appel ambigu à '{$context}' : plusieurs surcharges correspondent "
                . "aux types (" . $this->typesToString($argTypes) . ")",
                $file,
                $line,
                $column,
            );
        }

        // 5. Une seule candidate
        return $candidates[0];
    }

    /**
     * @param MethodInfo|FunctionInfo $overload
     * @param Type[] $argTypes
     */
    private function accepts(MethodInfo|FunctionInfo $overload, array $argTypes): bool
    {
        foreach ($overload->params as $i => $param) {
            $paramType = TypeFactory::fromNode(
                $param->type,
                $overload instanceof MethodInfo ? $overload->declaringClass : null,
            );
            if (!$this->subtypes->isSubtypeOf($argTypes[$i], $paramType)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param MethodInfo[]|FunctionInfo[] $overloads
     * @param Type[] $argTypes
     */
    private function noMatchMessage(array $overloads, array $argTypes, string $context): string
    {
        $lines = [];
        $lines[] = "Aucune surcharge de '{$context}' ne correspond aux types ("
            . $this->typesToString($argTypes) . ")";
        $lines[] = "Surcharges disponibles :";
        foreach ($overloads as $o) {
            $paramTypes = array_map(
                fn($p) => (string) TypeFactory::fromNode($p->type),
                $o->params,
            );
            $lines[] = '  - ' . $context . '(' . implode(', ', $paramTypes) . ')';
        }
        return implode("\n", $lines);
    }

    /**
     * @param Type[] $types
     */
    private function typesToString(array $types): string
    {
        return implode(', ', array_map('strval', $types));
    }
}