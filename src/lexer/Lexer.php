<?php

declare(strict_types=1);

namespace PPhp\Lexer;

use PPhp\Error\LexerError;

/**
 * Tokenise un programme PPHP.
 *
 * Contraintes :
 *  - Tout le fichier doit être à l'intérieur de <?php ... ?>
 *  - Aucun contenu hors balises (autre que des espaces) n'est autorisé
 *  - .5 (sans 0 initial) est interdit pour lever l'ambiguïté avec l'opérateur .
 *  - Les commentaires sont ignorés (non émis comme tokens)
 */
final class Lexer
{
    private string $source = '';
    private int $pos = 0;
    private int $line = 1;
    private int $column = 1;
    private string $file = '<input>';

    /** @var Token[] */
    private array $tokens = [];

    /** @var array<string, TokenType> */
    private array $keywords;

    public function __construct()
    {
        $this->keywords = $this->buildKeywordTable();
    }

    /**
     * @return Token[]
     */
    public function tokenize(string $source, string $file = '<input>'): array
    {
        $this->source = $source;
        $this->pos = 0;
        $this->line = 1;
        $this->column = 1;
        $this->file = $file;
        $this->tokens = [];

        $this->lexProgram();

        $this->emit(TokenType::Eof, '', null, $this->pos, $this->pos);

        return $this->tokens;
    }

    // =========================================================================
    //  Émission de tokens (centralisée)
    // =========================================================================

    /**
     * Émet un token en calculant les drapeaux d'espacement.
     *
     * @param int $startPos Offset brut du début du token dans la source
     * @param int $endPos   Offset brut de fin du token (exclu)
     */
    private function emit(
        TokenType $type,
        string $lexeme,
        mixed $value,
        int $startPos,
        int $endPos,
    ): void {
        $line = $this->lineOf($startPos);
        $col = $this->columnOf($startPos);

        $precededByWs = $startPos > 0 && $this->isWhitespace($this->source[$startPos - 1]);
        // Après la fin du token : espace, ou bien commentaire, ou EOF
        $followedByWs = true;
        if ($endPos < strlen($this->source)) {
            $c = $this->source[$endPos];
            $followedByWs = $this->isWhitespace($c)
                || ($c === '/' && ($this->source[$endPos + 1] ?? '') === '/')
                || ($c === '/' && ($this->source[$endPos + 1] ?? '') === '*')
                || ($c === '#');
        }

        $this->tokens[] = new Token($type, $lexeme, $value, $line, $col, $precededByWs, $followedByWs);
    }

    /**
     * Recalcule ligne/colonne à partir d'un offset brut.
     * Utile car on n'a pas conservé la position exacte au moment de l'émission.
     */
    private function lineOf(int $offset): int
    {
        $line = 1;
        for ($i = 0; $i < $offset && $i < strlen($this->source); $i++) {
            if ($this->source[$i] === "\n") {
                $line++;
            } elseif ($this->source[$i] === "\r") {
                if (($this->source[$i + 1] ?? '') === "\n") {
                    $i++;
                }
                $line++;
            }
        }
        return $line;
    }

    private function columnOf(int $offset): int
    {
        $col = 1;
        for ($i = 0; $i < $offset && $i < strlen($this->source); $i++) {
            $c = $this->source[$i];
            if ($c === "\n") {
                $col = 1;
            } elseif ($c === "\r") {
                if (($this->source[$i + 1] ?? '') === "\n") {
                    $i++;
                }
                $col = 1;
            } else {
                $col++;
            }
        }
        return $col;
    }

    // =========================================================================
    //  Pilotage
    // =========================================================================

    private function lexProgram(): void
    {
        $this->skipWhitespaceOnlyBeforeOpenTag();

        if (!$this->startsWith('<?php')) {
            $this->error("Le fichier doit commencer par '<?php' (aucun contenu hors balises autorisé)");
        }
        $openStart = $this->pos;
        $this->advance(5);
        if (!$this->atEnd() && !$this->isWhitespace($this->current())) {
            $this->error("Un espace ou un retour à la ligne est requis après '<?php'");
        }
        $this->emit(TokenType::OpenTag, '<?php', null, $openStart, $this->pos);

        while (!$this->atEnd()) {
            $this->skipWhitespaceAndComments();

            if ($this->atEnd()) {
                break;
            }

            if ($this->startsWith('?>')) {
                $closeStart = $this->pos;
                $this->advance(2);
                $this->emit(TokenType::CloseTag, '?>', null, $closeStart, $this->pos);
                $this->skipWhitespaceOnly();
                if (!$this->atEnd()) {
                    $this->error("Contenu hors balises PHP interdit");
                }
                break;
            }

            $this->lexToken();
        }
    }

    // =========================================================================
    //  Tokenisation d'un token
    // =========================================================================

    private function lexToken(): void
    {
        $c = $this->current();

        if ($c === '$') {
            $this->lexVariable();
            return;
        }
        if ($this->isIdentStart($c)) {
            $this->lexIdentifierOrKeyword();
            return;
        }
        if ($this->isDigit($c)) {
            $this->lexNumber();
            return;
        }
        if ($c === '"' || $c === "'") {
            $this->lexString($c);
            return;
        }
        if ($this->lexOperator()) {
            return;
        }

        $this->error("Caractère inattendu '" . $c . "'");
    }

    // =========================================================================
    //  Variables, identifiants, mots-clés
    // =========================================================================

    private function lexVariable(): void
    {
        $start = $this->pos;
        $this->advance(1); // consomme $

        if ($this->atEnd() || !$this->isIdentStart($this->current())) {
            $this->error("Nom de variable attendu après '$'");
        }

        while (!$this->atEnd() && $this->isIdentPart($this->current())) {
            $this->advance(1);
        }

        $lexeme = substr($this->source, $start, $this->pos - $start);
        $name = substr($lexeme, 1);

        if ($name === 'this') {
            $this->emit(TokenType::KwThis, $lexeme, 'this', $start, $this->pos);
            return;
        }

        $this->emit(TokenType::Variable, $lexeme, $name, $start, $this->pos);
    }

    private function lexIdentifierOrKeyword(): void
    {
        $start = $this->pos;

        while (!$this->atEnd() && $this->isIdentPart($this->current())) {
            $this->advance(1);
        }

        $lexeme = substr($this->source, $start, $this->pos - $start);
        $lower = strtolower($lexeme);

        if (isset($this->keywords[$lower])) {
            $type = $this->keywords[$lower];
            $value = match ($type) {
                TokenType::KwTrue => true,
                TokenType::KwFalse => false,
                TokenType::KwNull => null,
                default => $lexeme,
            };
            $this->emit($type, $lexeme, $value, $start, $this->pos);
            return;
        }

        $this->emit(TokenType::Identifier, $lexeme, $lexeme, $start, $this->pos);
    }

    // =========================================================================
    //  Nombres
    // =========================================================================

    private function lexNumber(): void
    {
        $start = $this->pos;

        if ($this->startsWith('0x') || $this->startsWith('0X')) {
            $this->advance(2);
            $digitStart = $this->pos;
            while (!$this->atEnd() && (ctype_xdigit($this->current()) || $this->current() === '_')) {
                $this->advance(1);
            }
            if ($this->pos === $digitStart) {
                $this->error("Nombre hexadécimal mal formé : aucun chiffre après '0x'");
            }
            $lexeme = substr($this->source, $start, $this->pos - $start);
            $clean = str_replace('_', '', substr($lexeme, 2));
            $this->emit(TokenType::IntLiteral, $lexeme, intval($clean, 16), $start, $this->pos);
            return;
        }

        if ($this->startsWith('0b') || $this->startsWith('0B')) {
            $this->advance(2);
            $digitStart = $this->pos;
            while (!$this->atEnd() && ($this->current() === '0' || $this->current() === '1' || $this->current() === '_')) {
                $this->advance(1);
            }
            if ($this->pos === $digitStart) {
                $this->error("Nombre binaire mal formé : aucun chiffre après '0b'");
            }
            $lexeme = substr($this->source, $start, $this->pos - $start);
            $clean = str_replace('_', '', substr($lexeme, 2));
            $this->emit(TokenType::IntLiteral, $lexeme, intval($clean, 2), $start, $this->pos);
            return;
        }

        if ($this->startsWith('0o') || $this->startsWith('0O')) {
            $this->advance(2);
            $digitStart = $this->pos;
            while (!$this->atEnd() && (($this->current() >= '0' && $this->current() <= '7') || $this->current() === '_')) {
                $this->advance(1);
            }
            if ($this->pos === $digitStart) {
                $this->error("Nombre octal mal formé : aucun chiffre après '0o'");
            }
            $lexeme = substr($this->source, $start, $this->pos - $start);
            $clean = str_replace('_', '', substr($lexeme, 2));
            $this->emit(TokenType::IntLiteral, $lexeme, intval($clean, 8), $start, $this->pos);
            return;
        }

        $isFloat = false;

        while (!$this->atEnd() && ($this->isDigit($this->current()) || $this->current() === '_')) {
            $this->advance(1);
        }

        if (!$this->atEnd() && $this->current() === '.' && $this->peek(1) !== null && $this->isDigit($this->peek(1))) {
            $isFloat = true;
            $this->advance(1);
            while (!$this->atEnd() && ($this->isDigit($this->current()) || $this->current() === '_')) {
                $this->advance(1);
            }
        }

        if (!$this->atEnd() && ($this->current() === 'e' || $this->current() === 'E')) {
            $save = $this->pos;
            $this->advance(1);
            if (!$this->atEnd() && ($this->current() === '+' || $this->current() === '-')) {
                $this->advance(1);
            }
            if (!$this->atEnd() && $this->isDigit($this->current())) {
                $isFloat = true;
                while (!$this->atEnd() && ($this->isDigit($this->current()) || $this->current() === '_')) {
                    $this->advance(1);
                }
            } else {
                $this->pos = $save;
            }
        }

        $lexeme = substr($this->source, $start, $this->pos - $start);
        $clean = str_replace('_', '', $lexeme);

        if ($isFloat) {
            $this->emit(TokenType::FloatLiteral, $lexeme, (float) $clean, $start, $this->pos);
        } else {
            $this->emit(TokenType::IntLiteral, $lexeme, (int) $clean, $start, $this->pos);
        }
    }

    // =========================================================================
    //  Chaînes
    // =========================================================================

    private function lexString(string $quote): void
    {
        $start = $this->pos;
        $startLine = $this->line;

        $this->advance(1);

        while (!$this->atEnd()) {
            $c = $this->current();

            if ($c === '\\') {
                $this->advance(1);
                if (!$this->atEnd()) {
                    $this->advance(1);
                }
                continue;
            }

            if ($c === $quote) {
                $this->advance(1);
                $lexeme = substr($this->source, $start, $this->pos - $start);
                $raw = substr($lexeme, 1, -1);
                $this->emit(TokenType::StringLiteral, $lexeme, $raw, $start, $this->pos);
                return;
            }

            $this->advance(1);
        }

        $this->error("Chaîne non terminée (guillemet ouvrant à la ligne $startLine)");
    }

    // =========================================================================
    //  Opérateurs
    // =========================================================================

    private function lexOperator(): bool
    {
        static $ops = null;
        if ($ops === null) {
            $ops = [
                '?->' => TokenType::NullsafeArrow,
                '<=>' => TokenType::Spaceship,
                '===' => TokenType::Identical,
                '!==' => TokenType::NotIdentical,
                '**=' => TokenType::PowerAssign,
                '<<=' => TokenType::ShlAssign,
                '>>=' => TokenType::ShrAssign,
                '??=' => TokenType::CoalesceAssign,
                '...' => TokenType::Ellipsis,
                '??'  => TokenType::NullCoalesce,
                '->'  => TokenType::Arrow,
                '::'  => TokenType::DoubleColon,
                '=>'  => TokenType::DoubleArrow,
                '=='  => TokenType::Equal,
                '!='  => TokenType::NotEqual,
                '<='  => TokenType::LessEqual,
                '>='  => TokenType::GreaterEqual,
                '&&'  => TokenType::And,
                '||'  => TokenType::Or,
                '++'  => TokenType::Increment,
                '--'  => TokenType::Decrement,
                '+='  => TokenType::PlusAssign,
                '-='  => TokenType::MinusAssign,
                '*='  => TokenType::StarAssign,
                '/='  => TokenType::SlashAssign,
                '%='  => TokenType::PercentAssign,
                '.='  => TokenType::DotAssign,
                '&='  => TokenType::AndAssign,
                '|='  => TokenType::OrAssign,
                '^='  => TokenType::XorAssign,
                '**'  => TokenType::Power,
                '<<'  => TokenType::Shl,
                '>>'  => TokenType::Shr,
                '+'   => TokenType::Plus,
                '-'   => TokenType::Minus,
                '*'   => TokenType::Star,
                '/'   => TokenType::Slash,
                '%'   => TokenType::Percent,
                '='   => TokenType::Assign,
                '<'   => TokenType::Less,
                '>'   => TokenType::Greater,
                '!'   => TokenType::Not,
                '&'   => TokenType::BitAnd,
                '|'   => TokenType::BitOr,
                '^'   => TokenType::BitXor,
                '~'   => TokenType::BitNot,
                '.'   => TokenType::Dot,
                '?'   => TokenType::Question,
                ':'   => TokenType::Colon,
                ';'   => TokenType::Semicolon,
                ','   => TokenType::Comma,
                '('   => TokenType::LParen,
                ')'   => TokenType::RParen,
                '{'   => TokenType::LBrace,
                '}'   => TokenType::RBrace,
                '['   => TokenType::LBracket,
                ']'   => TokenType::RBracket,
                '\\'  => TokenType::Backslash,
                '@'   => TokenType::At,
            ];
        }

        foreach ($ops as $lexeme => $type) {
            if ($this->startsWith($lexeme)) {
                $start = $this->pos;
                $this->advance(strlen($lexeme));
                $this->emit($type, $lexeme, $lexeme, $start, $this->pos);
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    //  Gestion de la position
    // =========================================================================

    private function atEnd(): bool
    {
        return $this->pos >= strlen($this->source);
    }

    private function current(): string
    {
        return $this->source[$this->pos] ?? '';
    }

    private function peek(int $offset): ?string
    {
        $p = $this->pos + $offset;
        if ($p < 0 || $p >= strlen($this->source)) {
            return null;
        }
        return $this->source[$p];
    }

    private function startsWith(string $s): bool
    {
        return substr($this->source, $this->pos, strlen($s)) === $s;
    }

    private function advance(int $n = 1): void
    {
        for ($i = 0; $i < $n; $i++) {
            if ($this->atEnd()) {
                return;
            }
            $c = $this->source[$this->pos];
            $this->pos++;

            if ($c === "\n") {
                $this->line++;
                $this->column = 1;
            } elseif ($c === "\r") {
                if ($this->pos < strlen($this->source) && $this->source[$this->pos] === "\n") {
                    $this->pos++;
                }
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }
        }
    }

    // =========================================================================
    //  Sauts
    // =========================================================================

    private function skipWhitespaceAndComments(): void
    {
        while (!$this->atEnd()) {
            $c = $this->current();

            if ($this->isWhitespace($c)) {
                $this->advance(1);
                continue;
            }
            if ($c === '/' && $this->peek(1) === '/') {
                $this->skipToEndOfLine();
                continue;
            }
            if ($c === '#') {
                $this->skipToEndOfLine();
                continue;
            }
            if ($c === '/' && $this->peek(1) === '*') {
                $this->skipBlockComment();
                continue;
            }
            break;
        }
    }

    private function skipWhitespaceOnlyBeforeOpenTag(): void
    {
        if (substr($this->source, 0, 3) === "\xEF\xBB\xBF") {
            $this->pos = 3;
            $this->column = 1;
        }
        $this->skipWhitespaceOnly();
    }

    private function skipWhitespaceOnly(): void
    {
        while (!$this->atEnd() && $this->isWhitespace($this->current())) {
            $this->advance(1);
        }
    }

    private function skipToEndOfLine(): void
    {
        while (!$this->atEnd() && $this->current() !== "\n" && $this->current() !== "\r") {
            $this->advance(1);
        }
    }

    private function skipBlockComment(): void
    {
        $startLine = $this->line;
        $this->advance(2);
        while (!$this->atEnd()) {
            if ($this->current() === '*' && $this->peek(1) === '/') {
                $this->advance(2);
                return;
            }
            $this->advance(1);
        }
        $this->error("Commentaire /* non terminé (ouvert à la ligne $startLine)");
    }

    // =========================================================================
    //  Prédicats
    // =========================================================================

    private function isWhitespace(string $c): bool
    {
        return $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" || $c === "\v" || $c === "\f";
    }

    private function isDigit(string $c): bool
    {
        return $c >= '0' && $c <= '9';
    }

    private function isIdentStart(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z')
            || ($c >= 'A' && $c <= 'Z')
            || $c === '_'
            || ord($c) >= 0x80;
    }

    private function isIdentPart(string $c): bool
    {
        return $this->isIdentStart($c) || $this->isDigit($c);
    }

    // =========================================================================
    //  Mots-clés
    // =========================================================================

    private function buildKeywordTable(): array
    {
        return [
            'if' => TokenType::KwIf, 'else' => TokenType::KwElse, 'elseif' => TokenType::KwElseIf,
            'while' => TokenType::KwWhile, 'do' => TokenType::KwDo, 'for' => TokenType::KwFor,
            'foreach' => TokenType::KwForeach, 'as' => TokenType::KwAs,
            'switch' => TokenType::KwSwitch, 'case' => TokenType::KwCase, 'default' => TokenType::KwDefault,
            'break' => TokenType::KwBreak, 'continue' => TokenType::KwContinue,
            'return' => TokenType::KwReturn, 'yield' => TokenType::KwYield,
            'match' => TokenType::KwMatch, 'fn' => TokenType::KwFn,
            'function' => TokenType::KwFunction, 'class' => TokenType::KwClass,
            'interface' => TokenType::KwInterface, 'trait' => TokenType::KwTrait, 'enum' => TokenType::KwEnum,
            'extends' => TokenType::KwExtends, 'implements' => TokenType::KwImplements,
            'abstract' => TokenType::KwAbstract, 'final' => TokenType::KwFinal,
            'const' => TokenType::KwConst, 'static' => TokenType::KwStatic,
            'readonly' => TokenType::KwReadonly, 'var' => TokenType::KwVar,
            'new' => TokenType::KwNew, 'clone' => TokenType::KwClone, 'instanceof' => TokenType::KwInstanceof,
            'public' => TokenType::KwPublic, 'protected' => TokenType::KwProtected, 'private' => TokenType::KwPrivate,
            'int' => TokenType::KwInt, 'float' => TokenType::KwFloat, 'bool' => TokenType::KwBool,
            'string' => TokenType::KwString, 'array' => TokenType::KwArray, 'object' => TokenType::KwObject,
            'mixed' => TokenType::KwMixed, 'void' => TokenType::KwVoid, 'never' => TokenType::KwNever,
            'null' => TokenType::KwNull, 'false' => TokenType::KwFalse, 'true' => TokenType::KwTrue,
            'callable' => TokenType::KwCallable, 'iterable' => TokenType::KwIterable,
            'self' => TokenType::KwSelf, 'parent' => TokenType::KwParent, 'this' => TokenType::KwThis,
        ];
    }

    private function error(string $message): never
    {
        throw new LexerError($message, $this->file, $this->line, $this->column);
    }
}