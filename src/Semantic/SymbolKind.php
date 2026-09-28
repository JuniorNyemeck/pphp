<?php

declare(strict_types=1);

namespace PPhp\Semantic;

enum SymbolKind: string
{
    case Variable = 'variable';
    case Parameter = 'parameter';
    case Property = 'property';
    case Method = 'method';
    case Func = 'function';
    case ClassType = 'class';
    case InterfaceType = 'interface';
}