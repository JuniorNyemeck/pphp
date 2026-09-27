<?php

declare(strict_types=1);

namespace PPhp\Tests\Lexer;

use PPhp\Error\LexerError;
use PPhp\Lexer\Lexer;
use PPhp\Lexer\Token;
use PPhp\Lexer\TokenType;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    // =========================================================================
    //  Utilitaires
    // =========================================================================

    /**
     * @param TokenType[] $expectedTypes
     */
    private function assertTokens(string $source, array $expectedTypes): array
    {
        $tokens = $this->lexer->tokenize($source);
        // Retire l'Eof pour la comparaison si non attendu
        $actualTypes = array_map(fn(Token $t) => $t->type, $tokens);

        // On ajoute toujours Eof en fin des attendus si pas déjà présent
        if (end($expectedTypes) !== TokenType::Eof) {
            $expectedTypes[] = TokenType::Eof;
        }

        $this->assertSame(
            $expectedTypes,
            $actualTypes,
            'Types de tokens inattendus. Tokens réels : ' . implode(', ', array_map(
                fn(Token $t) => $t->type->name . '(' . $t->lexeme . ')',
                $tokens
            ))
        );

        return $tokens;
    }

    private function assertLexError(string $source, string $expectedMessagePart): void
    {
        try {
            $this->lexer->tokenize($source);
            $this->fail('Une LexerError était attendue.');
        } catch (LexerError $e) {
            $this->assertStringContainsString($expectedMessagePart, $e->getMessage());
        }
    }

    // =========================================================================
    //  Balises
    // =========================================================================

    public function testEmptyProgram(): void
    {
        $this->assertTokens('<?php ', [TokenType::OpenTag]);
    }

    public function testOpenTagRequired(): void
    {
        $this->assertLexError('echo "x";', 'doit commencer par');
    }

    public function testContentOutsideTagsForbidden(): void
    {
        $this->assertLexError("<?php echo 1; ?>\n<p>hello</p>", 'hors balises');
    }

    public function testSpaceRequiredAfterOpenTag(): void
    {
        $this->assertLexError('<?phpfoo', "espace ou un retour à la ligne");
    }

    public function testContentOutsideTagsBeforePhpForbidden(): void
    {
        $this->assertLexError('hello<?php ', 'doit commencer par');
    }

    // =========================================================================
    //  Identifiants et variables
    // =========================================================================

    public function testSimpleVariable(): void
    {
        $tokens = $this->assertTokens('<?php $foo', [
            TokenType::OpenTag,
            TokenType::Variable,
        ]);
        $this->assertSame('foo', $tokens[1]->value);
        $this->assertSame('$foo', $tokens[1]->lexeme);
    }

    public function testVariableWithDigitsAndUnderscore(): void
    {
        $tokens = $this->assertTokens('<?php $foo_bar123', [
            TokenType::OpenTag,
            TokenType::Variable,
        ]);
        $this->assertSame('foo_bar123', $tokens[1]->value);
    }

    public function testDollarWithoutName(): void
    {
        $this->assertLexError('<?php $ ', 'Nom de variable attendu');
    }

    public function testIdentifier(): void
    {
        $tokens = $this->assertTokens('<?php myFunc', [
            TokenType::OpenTag,
            TokenType::Identifier,
        ]);
        $this->assertSame('myFunc', $tokens[1]->value);
    }

    // =========================================================================
    //  Mots-clés
    // =========================================================================

    public function testKeywords(): void
    {
        $this->assertTokens('<?php if else while function class return', [
            TokenType::OpenTag,
            TokenType::KwIf,
            TokenType::KwElse,
            TokenType::KwWhile,
            TokenType::KwFunction,
            TokenType::KwClass,
            TokenType::KwReturn,
        ]);
    }

    public function testTypeKeywords(): void
    {
        $this->assertTokens('<?php int float bool string', [
            TokenType::OpenTag,
            TokenType::KwInt,
            TokenType::KwFloat,
            TokenType::KwBool,
            TokenType::KwString,
        ]);
    }

    public function testKeywordsAreCaseInsensitive(): void
    {
        $this->assertTokens('<?php IF ELSE Return', [
            TokenType::OpenTag,
            TokenType::KwIf,
            TokenType::KwElse,
            TokenType::KwReturn,
        ]);
    }

    public function testTrueFalseNullValues(): void
    {
        $tokens = $this->assertTokens('<?php true false null', [
            TokenType::OpenTag,
            TokenType::KwTrue,
            TokenType::KwFalse,
            TokenType::KwNull,
        ]);
        $this->assertTrue($tokens[1]->value);
        $this->assertFalse($tokens[2]->value);
        $this->assertNull($tokens[3]->value);
    }

    // =========================================================================
    //  Nombres
    // =========================================================================

    public function testIntLiteral(): void
    {
        $tokens = $this->assertTokens('<?php 42', [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
        $this->assertSame(42, $tokens[1]->value);
    }

    public function testIntWithUnderscores(): void
    {
        $tokens = $this->assertTokens('<?php 1_000_000', [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
        $this->assertSame(1000000, $tokens[1]->value);
    }

    public function testFloatLiteral(): void
    {
        $tokens = $this->assertTokens('<?php 3.14', [
            TokenType::OpenTag,
            TokenType::FloatLiteral,
        ]);
        $this->assertSame(3.14, $tokens[1]->value);
    }

    public function testFloatWithLeadingZero(): void
    {
        $tokens = $this->assertTokens('<?php 0.5', [
            TokenType::OpenTag,
            TokenType::FloatLiteral,
        ]);
        $this->assertSame(0.5, $tokens[1]->value);
    }

    public function testDotFiveIsLexedAsIntDotFive(): void
    {
        // .5 doit être lexé comme . puis 5, et c'est au parser de rejeter.
        $tokens = $this->assertTokens('<?php .5', [
            TokenType::OpenTag,
            TokenType::Dot,
            TokenType::IntLiteral,
        ]);
        $this->assertSame(5, $tokens[2]->value);
    }

    public function testHexLiteral(): void
    {
        $tokens = $this->assertTokens('<?php 0xFF', [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
        $this->assertSame(255, $tokens[1]->value);
    }

    public function testBinaryLiteral(): void
    {
        $tokens = $this->assertTokens('<?php 0b1010', [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
        $this->assertSame(10, $tokens[1]->value);
    }

    public function testOctalLiteral(): void
    {
        $tokens = $this->assertTokens('<?php 0o17', [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
        $this->assertSame(15, $tokens[1]->value);
    }

    public function testScientificNotation(): void
    {
        $tokens = $this->assertTokens('<?php 1.5e3', [
            TokenType::OpenTag,
            TokenType::FloatLiteral,
        ]);
        $this->assertSame(1500.0, $tokens[1]->value);
    }

    public function testScientificNotationWithSign(): void
    {
        $tokens = $this->assertTokens('<?php 1e-3', [
            TokenType::OpenTag,
            TokenType::FloatLiteral,
        ]);
        $this->assertSame(0.001, $tokens[1]->value);
    }

    // =========================================================================
    //  Chaînes
    // =========================================================================

    public function testSimpleString(): void
    {
        $tokens = $this->assertTokens('<?php "hello"', [
            TokenType::OpenTag,
            TokenType::StringLiteral,
        ]);
        $this->assertSame('hello', $tokens[1]->value);
        $this->assertSame('"hello"', $tokens[1]->lexeme);
    }

    public function testSingleQuoteString(): void
    {
        $tokens = $this->assertTokens("<?php 'hello'", [
            TokenType::OpenTag,
            TokenType::StringLiteral,
        ]);
        $this->assertSame('hello', $tokens[1]->value);
    }

    public function testStringWithEscapedQuote(): void
    {
        $tokens = $this->assertTokens('<?php "he said \\"hi\\""', [
            TokenType::OpenTag,
            TokenType::StringLiteral,
        ]);
        // La valeur brute préserve les échappements
        $this->assertSame('he said \\"hi\\"', $tokens[1]->value);
    }

    public function testUnterminatedString(): void
    {
        $this->assertLexError('<?php "hello', 'Chaîne non terminée');
    }

    // =========================================================================
    //  Opérateurs
    // =========================================================================

    public function testArithmeticOperators(): void
    {
        $this->assertTokens('<?php + - * / % **', [
            TokenType::OpenTag,
            TokenType::Plus,
            TokenType::Minus,
            TokenType::Star,
            TokenType::Slash,
            TokenType::Percent,
            TokenType::Power,
        ]);
    }

    public function testComparisonOperators(): void
    {
        $this->assertTokens('<?php == === != !== < <= > >= <=>', [
            TokenType::OpenTag,
            TokenType::Equal,
            TokenType::Identical,
            TokenType::NotEqual,
            TokenType::NotIdentical,
            TokenType::Less,
            TokenType::LessEqual,
            TokenType::Greater,
            TokenType::GreaterEqual,
            TokenType::Spaceship,
        ]);
    }

    public function testArrowAndDot(): void
    {
        $this->assertTokens('<?php -> . ?-> ::', [
            TokenType::OpenTag,
            TokenType::Arrow,
            TokenType::Dot,
            TokenType::NullsafeArrow,
            TokenType::DoubleColon,
        ]);
    }

    public function testLongestMatchWins(): void
    {
        // === doit être un seul token, pas deux == puis =
        $this->assertTokens('<?php ===', [
            TokenType::OpenTag,
            TokenType::Identical,
        ]);
    }

    public function testAssignments(): void
    {
        $this->assertTokens('<?php = += -= *= /= .= %= **= ??=', [
            TokenType::OpenTag,
            TokenType::Assign,
            TokenType::PlusAssign,
            TokenType::MinusAssign,
            TokenType::StarAssign,
            TokenType::SlashAssign,
            TokenType::DotAssign,
            TokenType::PercentAssign,
            TokenType::PowerAssign,
            TokenType::CoalesceAssign,
        ]);
    }

    public function testNullCoalesceAndTernary(): void
    {
        $this->assertTokens('<?php ?? ? :', [
            TokenType::OpenTag,
            TokenType::NullCoalesce,
            TokenType::Question,
            TokenType::Colon,
        ]);
    }

    public function testEllipsisAndDoubleArrow(): void
    {
        $this->assertTokens('<?php ... =>', [
            TokenType::OpenTag,
            TokenType::Ellipsis,
            TokenType::DoubleArrow,
        ]);
    }

    public function testIncrementDecrement(): void
    {
        $this->assertTokens('<?php ++ --', [
            TokenType::OpenTag,
            TokenType::Increment,
            TokenType::Decrement,
        ]);
    }

    // =========================================================================
    //  Commentaires
    // =========================================================================

    public function testLineComment(): void
    {
        $this->assertTokens("<?php // ceci est un commentaire\n42", [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
    }

    public function testHashComment(): void
    {
        $this->assertTokens("<?php # commentaire\n42", [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
    }

    public function testBlockComment(): void
    {
        $this->assertTokens('<?php /* commentaire */ 42', [
            TokenType::OpenTag,
            TokenType::IntLiteral,
        ]);
    }

    public function testUnterminatedBlockComment(): void
    {
        $this->assertLexError('<?php /* pas fini', 'Commentaire /* non terminé');
    }

    // =========================================================================
    //  Positions
    // =========================================================================

    public function testPositions(): void
    {
        $tokens = $this->lexer->tokenize("<?php\n\$a = 1;\n\$b = 2;");
        // OpenTag @ 1:1, $a @ 2:1, = @ 2:4, 1 @ 2:6, ; @ 2:7,
        // $b @ 3:1, = @ 3:4, 2 @ 3:6, ; @ 3:7, Eof
        $this->assertSame(1, $tokens[0]->line);
        $this->assertSame(1, $tokens[0]->column);

        $this->assertSame(2, $tokens[1]->line);
        $this->assertSame(1, $tokens[1]->column);

        $this->assertSame(2, $tokens[2]->line);
        $this->assertSame(4, $tokens[2]->column);

        $this->assertSame(3, $tokens[5]->line);
        $this->assertSame(1, $tokens[5]->column);
    }

    // =========================================================================
    //  Caractère inattendu
    // =========================================================================

    public function testUnexpectedCharacter(): void
    {
        $this->assertLexError('<?php `', 'Caractère inattendu');
    }

    // =========================================================================
    //  Programme complet
    // =========================================================================

    public function testCompleteSnippet(): void
    {
        $source = <<<'PPHP'
        <?php
        int $a = 5;
        int $b = $a + 3;
        function f(int $x): int {
            return $x * 2;
        }
        PPHP;

        $tokens = $this->lexer->tokenize($source);

        // Juste vérifier que ça ne lève pas d'erreur et qu'on a bien un Eof
        $last = end($tokens);
        $this->assertSame(TokenType::Eof, $last->type);

        // Vérifier que "int" est bien un KwInt et pas un Identifier
        $this->assertSame(TokenType::KwInt, $tokens[1]->type);
        $this->assertSame(TokenType::Variable, $tokens[2]->type);
    }

        // =========================================================================
    //  Espacement (pour la règle du '.')
    // =========================================================================

    public function testDotWithoutSpaces(): void
    {
        $tokens = $this->lexer->tokenize('<?php $a.$b');
        // tokens : OpenTag, $a, ., $b, Eof
        $dot = $tokens[2];
        $this->assertSame(TokenType::Dot, $dot->type);
        $this->assertFalse($dot->precededByWhitespace);
        $this->assertFalse($dot->followedByWhitespace);
    }

    public function testDotWithSpacesOnBothSides(): void
    {
        $tokens = $this->lexer->tokenize('<?php $a . $b');
        $dot = $tokens[2];
        $this->assertSame(TokenType::Dot, $dot->type);
        $this->assertTrue($dot->precededByWhitespace);
        $this->assertTrue($dot->followedByWhitespace);
    }

    public function testDotWithSpaceBeforeOnly(): void
    {
        $tokens = $this->lexer->tokenize('<?php $a .$b');
        $dot = $tokens[2];
        $this->assertTrue($dot->precededByWhitespace);
        $this->assertFalse($dot->followedByWhitespace);
    }

    public function testDotWithSpaceAfterOnly(): void
    {
        $tokens = $this->lexer->tokenize('<?php $a. $b');
        $dot = $tokens[2];
        $this->assertFalse($dot->precededByWhitespace);
        $this->assertTrue($dot->followedByWhitespace);
    }

        public function testWhitespaceFlagsOnArithmetic(): void
    {
        $tokens = $this->lexer->tokenize('<?php 1 + 2');
        // OpenTag, 1, +, 2, Eof
        $this->assertTrue($tokens[2]->precededByWhitespace);
        $this->assertTrue($tokens[2]->followedByWhitespace);
    }
}