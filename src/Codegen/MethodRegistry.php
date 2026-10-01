<?php

declare(strict_types=1);

namespace PPhp\Codegen;

use PPhp\Semantic\GlobalScope;

/**
 * Dictionnaire des noms PHP mangleds pour les méthodes et fonctions.
 *
 * Construit une fois à partir du GlobalScope, puis consulté par le PhpGenerator.
 */
final class MethodRegistry
{
    /**
     * @var array<string, array<string, string[]>>  class => methodName => [mangled1, ...]
     */
    private array $methods = [];

    /**
     * @var array<string, string[]>  functionName => [mangled1, ...]
     */
    private array $functions = [];

    /**
     * @var array<string, string[]>  class => list of __construct mangled names
     */
    private array $constructors = [];
    /**
     * @var array<string, \PPhp\Semantic\MethodInfo[]>  class => list of __construct MethodInfo
     */
    private array $constructorInfos = [];

    public function __construct(
        private readonly GlobalScope $globals,
        private readonly NameMangler $mangler = new NameMangler(),
    ) {
        $this->build();
    }

    private function build(): void
    {
        // Méthodes
        foreach ($this->globals->classes as $className => $class) {
            foreach ($class->methods as $methodName => $overloads) {
                $mangledList = [];
                foreach ($overloads as $overload) {
                    $mangledList[] = $this->mangler->methodName($overload, $overloads);
                }
                $this->methods[$className][$methodName] = $mangledList;

                if ($methodName === '__construct') {
                    $this->constructors[$className] = $mangledList;
                    $this->constructorInfos[$className] = $overloads;
                }
            }
        }

        // Fonctions
        foreach ($this->globals->functions as $funcName => $overloads) {
            $list = [];
            foreach ($overloads as $overload) {
                $list[] = $this->mangler->functionName($overload, $overloads);
            }
            $this->functions[$funcName] = $list;
        }
    }

    /**
     * Nom PHP final d'une méthode, selon l'indice de sa surcharge.
     */
    public function methodName(string $className, string $methodName, int $overloadIndex = 0): string
    {
        $list = $this->methods[$className][$methodName] ?? null;
        if ($list === null) {
            return $methodName;
        }
        return $list[$overloadIndex] ?? $methodName;
    }

    /**
     * Nom PHP final d'une fonction top-level, selon l'indice de sa surcharge.
     */
    public function functionName(string $funcName, int $overloadIndex = 0): string
    {
        $list = $this->functions[$funcName] ?? null;
        if ($list === null) {
            return $funcName;
        }
        return $list[$overloadIndex] ?? $funcName;
    }

    /**
     * Indique si la classe a un constructeur surchargé (à dispatcher).
     */
       public function hasConstructorDispatcher(string $className): bool
    {
        return isset($this->constructorInfos[$className])
            && count($this->constructorInfos[$className]) > 1;
    }

    /**
     * @return string[]  noms mangleds des surcharges de __construct
     */
    public function constructorOverloads(string $className): array
    {
        return $this->constructors[$className] ?? [];
    }

    public function mangler(): NameMangler
    {
        return $this->mangler;
    }

        /**
     * @return \PPhp\Semantic\MethodInfo[]
     */
    public function constructorInfos(string $className): array
    {
        return $this->constructorInfos[$className] ?? [];
    }
}