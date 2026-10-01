<?php

declare(strict_types=1);

namespace PPhp\Codegen;

use PPhp\Parser\Node\Param;
use PPhp\Parser\Node\TypeNode;
use PPhp\Semantic\FunctionInfo;
use PPhp\Semantic\MethodInfo;

/**
 * Encode les signatures en noms PHP sûrs.
 *
 * Conventions :
 *  - int         → int
 *  - ?int        → n_int
 *  - int[]       → a_int
 *  - int[][]     → a2_int
 *  - int|string  → u_int_string
 *  - ?int|string → u_n_int_string
 *  - Foo         → Foo
 *  - mixed       → mixed
 *  - array       → array
 */
final class NameMangler
{
    public const SEPARATOR = '__';

    /**
     * Nom PHP final d'une méthode.
     *
     * Si la méthode a plusieurs surcharges dans sa classe, on mangle.
     * Sinon, on garde le nom original.
     */
    public function methodName(MethodInfo $method, array $overloads): string
    {
        if (count($overloads) <= 1) {
            return $method->name;
        }
        return $method->name . self::SEPARATOR . $this->paramsSignature($method->params);
    }

    /**
     * Nom PHP final d'une fonction top-level.
     */
    public function functionName(FunctionInfo $func, array $overloads): string
    {
        if (count($overloads) <= 1) {
            return $func->name;
        }
        return $func->name . self::SEPARATOR . $this->paramsSignature($func->params);
    }

    /**
     * Nom du constructeur dispatcher (jamais mangled).
     */
    public function constructorDispatcherName(): string
    {
        return '__construct';
    }

    /**
     * Nom d'une surcharge de constructeur.
     */
    public function constructorOverloadName(MethodInfo $method): string
    {
        return '__construct' . self::SEPARATOR . $this->paramsSignature($method->params);
    }

    /**
     * Encode une liste de paramètres en une chaîne sûre.
     *
     * @param Param[] $params
     */
    public function paramsSignature(array $params): string
    {
        if (empty($params)) {
            return 'void';
        }
        return implode('_', array_map(
            fn(Param $p) => $this->typeSignature($p->type),
            $params,
        ));
    }

    /**
     * Encode un type en une chaîne sûre pour un nom de méthode.
     */
    public function typeSignature(TypeNode $type): string
    {
        $parts = [];

        // Préfixe nullable
        if ($type->nullable) {
            $parts[] = 'n';
        }

        // Tableaux imbriqués : a, a2, a3...
        if ($type->arrayDepth > 0) {
            $parts[] = 'a' . ($type->arrayDepth > 1 ? $type->arrayDepth : '');
        }

        // Union : u_type1_type2
        if (!empty($type->union)) {
            $parts[] = 'u';
            $parts[] = $this->simpleTypeName($type->name);
            foreach ($type->union as $u) {
                $parts[] = $this->typeSignature($u);
            }
        } else {
            $parts[] = $this->simpleTypeName($type->name);
        }

        return implode('_', array_filter($parts));
    }

    private function simpleTypeName(string $name): string
    {
        // Remplace les caractères non-alphanumériques (backslash de namespace)
        return str_replace('\\', '_', $name);
    }
}