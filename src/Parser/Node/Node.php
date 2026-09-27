<?php

declare(strict_types=1);

namespace PPhp\Parser\Node;

interface Node
{
    public function line(): int;
    public function column(): int;
}