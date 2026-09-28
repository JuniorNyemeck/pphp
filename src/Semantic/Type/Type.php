<?php

declare(strict_types=1);

namespace PPhp\Semantic\Type;

/**
 * Interface commune à tous les types sémantiques.
 */
interface Type
{
    /**
     * Représentation textuelle du type (pour messages d'erreur).
     */
    public function __toString(): string;

    /**
     * Égalité structurelle : deux types sont identiques si leur
     * représentation canonique l'est.
     */
    public function equals(Type $other): bool;
}