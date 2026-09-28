<?php

declare(strict_types=1);

namespace PPhp\Semantic;

final class ClassInfo
{
    /** @var array<string, PropertyInfo> */
    public array $properties = [];

    /** @var array<string, MethodInfo> */
    public array $methods = [];

    /**
     * @param string[] $modifiers
     * @param string[] $implements
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $parent,
        public readonly array $implements,
        public readonly array $modifiers,
        public readonly bool $isInterface,
        public readonly int $line,
        public readonly int $column,
    ) {}

    public function isAbstract(): bool
    {
        return in_array('abstract', $this->modifiers, true);
    }
}