<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

/**
 * A|B — union de types. Au moins deux membres.
 */
final class UnionType implements Type
{
    /** @var Type[] */
    public readonly array $members;

    /**
     * @param Type[] $members
     */
    public function __construct(array $members)
    {
        if (count($members) < 2) {
            throw new \InvalidArgumentException('UnionType requiert au moins 2 membres');
        }
        $this->members = $members;
    }

    public function __toString(): string
    {
        return implode('|', array_map('strval', $this->members));
    }

    public function equals(Type $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        if (count($this->members) !== count($other->members)) {
            return false;
        }
        // Comparaison ensembliste naïve
        foreach ($this->members as $a) {
            $found = false;
            foreach ($other->members as $b) {
                if ($a->equals($b)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }
}