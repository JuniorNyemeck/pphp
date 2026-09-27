<?php

declare(strict_types=1);

namespace PPhp\Lexer;

enum TokenType: string
{
    // === Structure ===
    case OpenTag = '<?php';
    case CloseTag = '?>';

    // === Littéraux ===
    case Identifier = 'identifier';
    case Variable = 'variable';           // $foo
    case IntLiteral = 'int_literal';
    case FloatLiteral = 'float_literal';
    case StringLiteral = 'string_literal';

    // === Mots-clés de contrôle ===
    case KwIf = 'if';
    case KwElse = 'else';
    case KwElseIf = 'elseif';
    case KwWhile = 'while';
    case KwDo = 'do';
    case KwFor = 'for';
    case KwForeach = 'foreach';
    case KwAs = 'as';
    case KwSwitch = 'switch';
    case KwCase = 'case';
    case KwDefault = 'default';
    case KwBreak = 'break';
    case KwContinue = 'continue';
    case KwReturn = 'return';
    case KwYield = 'yield';
    case KwMatch = 'match';
    case KwFn = 'fn';
    case KwEcho = "echo";

    // === Mots-clés de déclaration ===
    case KwFunction = 'function';
    case KwClass = 'class';
    case KwInterface = 'interface';
    case KwTrait = 'trait';
    case KwEnum = 'enum';
    case KwExtends = 'extends';
    case KwImplements = 'implements';
    case KwAbstract = 'abstract';
    case KwFinal = 'final';
    case KwConst = 'const';
    case KwStatic = 'static';
    case KwReadonly = 'readonly';
    case KwVar = 'var';
    case KwNew = 'new';
    case KwClone = 'clone';
    case KwInstanceof = 'instanceof';

    // === Modificateurs de visibilité ===
    case KwPublic = 'public';
    case KwProtected = 'protected';
    case KwPrivate = 'private';

    // === Mots-clés de type ===
    case KwInt = 'int';
    case KwFloat = 'float';
    case KwBool = 'bool';
    case KwString = 'string';
    case KwArray = 'array';
    case KwObject = 'object';
    case KwMixed = 'mixed';
    case KwVoid = 'void';
    case KwNever = 'never';
    case KwNull = 'null';
    case KwFalse = 'false';
    case KwTrue = 'true';
    case KwCallable = 'callable';
    case KwIterable = 'iterable';
    case KwSelf = 'self';
    case KwParent = 'parent';
    case KwThis = 'this';

    // === Opérateurs arithmétiques ===
    case Plus = '+';
    case Minus = '-';
    case Star = '*';
    case Slash = '/';
    case Percent = '%';
    case Power = '**';

    // === Opérateurs d'affectation composée ===
    case PlusAssign = '+=';
    case MinusAssign = '-=';
    case StarAssign = '*=';
    case SlashAssign = '/=';
    case PercentAssign = '%=';
    case DotAssign = '.=';
    case PowerAssign = '**=';
    case AndAssign = '&=';
    case OrAssign = '|=';
    case XorAssign = '^=';
    case ShlAssign = '<<=';
    case ShrAssign = '>>=';
    case CoalesceAssign = '??=';

    // === Opérateurs de comparaison ===
    case Assign = '=';
    case Equal = '==';
    case Identical = '===';
    case NotEqual = '!=';
    case NotIdentical = '!==';
    case Less = '<';
    case LessEqual = '<=';
    case Greater = '>';
    case GreaterEqual = '>=';
    case Spaceship = '<=>';

    // === Opérateurs logiques ===
    case And = '&&';
    case Or = '||';
    case Not = '!';
    case BitAnd = '&';
    case BitOr = '|';
    case BitXor = '^';
    case BitNot = '~';
    case Shl = '<<';
    case Shr = '>>';

    // === Opérateurs divers ===
    case Dot = '.';
    case Arrow = '->';
    case NullsafeArrow = '?->';
    case DoubleColon = '::';
    case DoubleArrow = '=>';
    case Question = '?';
    case NullCoalesce = '??';
    case Colon = ':';
    case Semicolon = ';';
    case Comma = ',';
    case Ellipsis = '...';
    case Backslash = '\\';
    case At = '@';
    case Increment = '++';
    case Decrement = '--';

    // === Délimiteurs ===
    case LParen = '(';
    case RParen = ')';
    case LBrace = '{';
    case RBrace = '}';
    case LBracket = '[';
    case RBracket = ']';

    // === Fin ===
    case Eof = 'eof';
}