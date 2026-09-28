<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Semantic\Type\Type;

final class PropertyInfo
{
    /**
     * @param string[] $modifiers
     */
    public function __construct(
        public readonly string $name,
        public readonly Type $type,
        public readonly array $modifiers,
        public readonly int $line,
        public readonly int $column,
    ) {}

    public function isPublic(): bool
    {
        return in_array('public', $this->modifiers, true) || empty($this->modifiers);
    }

    public function isStatic(): bool
    {
        return in_array('static', $this->modifiers, true);
    }
}