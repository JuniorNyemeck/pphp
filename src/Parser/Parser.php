<?php

declare(strict_types=1);

namespace PPhp\Parser;

use PPhp\Error\ParserError;
use PPhp\Lexer\Token;
use PPhp\Lexer\TokenType;
use PPhp\Parser\Node\Expr\AssignExpr;
use PPhp\Parser\Node\Expr\BinaryExpr;
use PPhp\Parser\Node\Expr\CallExpr;
use PPhp\Parser\Node\Expr\CoalesceExpr;
use PPhp\Parser\Node\Expr\DotAccessExpr;
use PPhp\Parser\Node\Expr\DotMethodCallExpr;
use PPhp\Parser\Node\Expr\Expr;
use PPhp\Parser\Node\Expr\IndexExpr;
use PPhp\Parser\Node\Expr\InstanceofExpr;
use PPhp\Parser\Node\Expr\LiteralExpr;
use PPhp\Parser\Node\Expr\MethodCallExpr;
use PPhp\Parser\Node\Expr\NewExpr;
use PPhp\Parser\Node\Expr\PostIncDecExpr;
use PPhp\Parser\Node\Expr\PreIncDecExpr;
use PPhp\Parser\Node\Expr\PropertyAccessExpr;
use PPhp\Parser\Node\Expr\TernaryExpr;
use PPhp\Parser\Node\Expr\UnaryExpr;
use PPhp\Parser\Node\Expr\VariableExpr;
use PPhp\Parser\Node\Param;
use PPhp\Parser\Node\Stmt\BlockStmt;
use PPhp\Parser\Node\Stmt\BreakStmt;
use PPhp\Parser\Node\Stmt\ClassDeclStmt;
use PPhp\Parser\Node\Stmt\ContinueStmt;
use PPhp\Parser\Node\Stmt\EchoStmt;
use PPhp\Parser\Node\Stmt\ExprStmt;
use PPhp\Parser\Node\Stmt\ForStmt;
use PPhp\Parser\Node\Stmt\ForeachStmt;
use PPhp\Parser\Node\Stmt\FunctionDeclStmt;
use PPhp\Parser\Node\Stmt\IfStmt;
use PPhp\Parser\Node\Stmt\MethodDeclStmt;
use PPhp\Parser\Node\Stmt\ProgramNode;
use PPhp\Parser\Node\Stmt\PropertyDeclStmt;
use PPhp\Parser\Node\Stmt\ReturnStmt;
use PPhp\Parser\Node\Stmt\Stmt;
use PPhp\Parser\Node\Stmt\VarDeclStmt;
use PPhp\Parser\Node\Stmt\WhileStmt;
use PPhp\Parser\Node\TypeNode;
use PPhp\Parser\Node\Expr\ThisExpr;
use PPhp\Parser\Node\Expr\ArrayLiteralExpr;    


 
 


/**
 * Parser PPHP — étape 2a. 
 *
 * Reconnaît : expressions complètes, statements simples (echo, return,
 * if/else/elseif, while, for, foreach, break, continue, blocs, expression
 * statements), et les accès `->` et `.`.
 *
 * Ne reconnaît pas encore : fonctions, classes, déclarations typées,
 * try/catch, switch, match, fn.
 */
final class Parser
{
    /** @var Token[] */
    private array $tokens = [];
    private int $pos = 0;
    private string $file = '<input>';

    /**
     * Table de précédence. Plus la valeur est haute, plus l'opérateur
     * est prioritaire.
     *
     * @var array<string, array{left: int, right: int}>
     */
    private const PRECEDENCE = [
        '='  => ['left' => 1,  'right' => 1],   // associatif à droite
        '+=' => ['left' => 1,  'right' => 1],
        '-=' => ['left' => 1,  'right' => 1],
        '*=' => ['left' => 1,  'right' => 1],
        '/=' => ['left' => 1,  'right' => 1],
        '%=' => ['left' => 1,  'right' => 1],
        '.=' => ['left' => 1,  'right' => 1],
        '**=' => ['left' => 1, 'right' => 1],
        '&=' => ['left' => 1,  'right' => 1],
        '|=' => ['left' => 1,  'right' => 1],
        '^=' => ['left' => 1,  'right' => 1],
        '<<=' => ['left' => 1, 'right' => 1],
        '>>=' => ['left' => 1, 'right' => 1],
        '??=' => ['left' => 1, 'right' => 1],

        '?'  => ['left' => 2,  'right' => 2],   // ternaire (traité à part)

        '??' => ['left' => 3,  'right' => 3],   // associatif à droite

        '||' => ['left' => 4,  'right' => 5],
        '&&' => ['left' => 6,  'right' => 7],

        '|'  => ['left' => 8,  'right' => 9],
        '^'  => ['left' => 10, 'right' => 11],
        '&'  => ['left' => 12, 'right' => 13],

        '==' => ['left' => 14, 'right' => 15],
        '!=' => ['left' => 14, 'right' => 15],
        '===' => ['left' => 14, 'right' => 15],
        '!==' => ['left' => 14, 'right' => 15],
        '<'  => ['left' => 16, 'right' => 17],
        '<=' => ['left' => 16, 'right' => 17],
        '>'  => ['left' => 16, 'right' => 17],
        '>=' => ['left' => 16, 'right' => 17],
        '<=>' => ['left' => 16, 'right' => 17],

        '<<' => ['left' => 18, 'right' => 19],
        '>>' => ['left' => 18, 'right' => 19],

        '+'  => ['left' => 20, 'right' => 21],
        '-'  => ['left' => 20, 'right' => 21],
        '.'  => ['left' => 20, 'right' => 21],   

        '*'  => ['left' => 22, 'right' => 23],
        '/'  => ['left' => 22, 'right' => 23],
        '%'  => ['left' => 22, 'right' => 23],

        '**' => ['left' => 24, 'right' => 24],
          // associatif à droite
    ];

    /**
     * @param Token[] $tokens
     */
    public function parse(array $tokens, string $file = '<input>'): ProgramNode
    {
        $this->tokens = $tokens;
        $this->pos = 0;
        $this->file = $file;

        return $this->parseProgram();
    }

    // =========================================================================
    //  Programme
    // =========================================================================

    private function parseProgram(): ProgramNode
    {
        $open = $this->expect(TokenType::OpenTag, "Le programme doit commencer par '<?pphp'");
        $statements = [];

        while (!$this->check(TokenType::Eof) && !$this->check(TokenType::CloseTag)) {
            $statements[] = $this->parseStatement();
        }

       
        if ($this->check(TokenType::CloseTag)) {
            $this->advance();
        }
        $this->expect(TokenType::Eof, 'Fin de fichier attendue');

        return new ProgramNode($open->line, $open->column, $statements);
    }

    // =========================================================================
    //  Statements
    // =========================================================================

    private function parseStatement(): Stmt
    {
        $t = $this->current();

        return match (true) {
            $t->type === TokenType::LBrace          => $this->parseBlock(),
            $t->type === TokenType::KwEcho          => $this->parseEcho(),
            $t->type === TokenType::KwReturn        => $this->parseReturn(),
            $t->type === TokenType::KwIf            => $this->parseIf(),
            $t->type === TokenType::KwWhile         => $this->parseWhile(),
            $t->type === TokenType::KwFor           => $this->parseFor(),
            $t->type === TokenType::KwForeach       => $this->parseForeach(),
            $t->type === TokenType::KwBreak         => $this->parseBreak(),
            $t->type === TokenType::KwContinue      => $this->parseContinue(),
            $t->type === TokenType::KwFunction      => $this->parseFunctionDecl(),
            $t->type === TokenType::KwClass         => $this->parseClassDecl(false),
            $t->type === TokenType::KwInterface     => $this->parseClassDecl(true),
            $t->type === TokenType::KwFinal         => $this->parseClassWithModifiers(),
            $t->type === TokenType::KwAbstract      => $this->parseClassWithModifiers(),
            default => $this->looksLikeTypedDeclaration()
                        ? $this->parseVarDecl()
                        : $this->parseExpressionStatement(),
        };
    }

    private function parseBlock(): BlockStmt
    {
        $open = $this->expect(TokenType::LBrace, "'{' attendu");
        $statements = [];
        while (!$this->check(TokenType::RBrace) && !$this->check(TokenType::Eof)) {
            $statements[] = $this->parseStatement();
        }
        $this->expect(TokenType::RBrace, "'}' attendu");
        return new BlockStmt($open->line, $open->column, $statements);
    }

    private function parseEcho(): EchoStmt
    {
        $kw = $this->advance();
        $exprs = [$this->parseExpression()];
        while ($this->check(TokenType::Comma)) {
            $this->advance();
            $exprs[] = $this->parseExpression();
        }
        $this->expectSemicolon();
        return new EchoStmt($kw->line, $kw->column, $exprs);
    }

    private function parseReturn(): ReturnStmt
    {
        $kw = $this->advance();
        $value = null;
        if (!$this->check(TokenType::Semicolon)) {
            $value = $this->parseExpression();
        }
        $this->expectSemicolon();
        return new ReturnStmt($kw->line, $kw->column, $value);
    }

    private function parseBreak(): BreakStmt
    {
        $kw = $this->advance();
        $level = null;
        if ($this->check(TokenType::IntLiteral)) {
            $level = (int) $this->advance()->value;
        }
        $this->expectSemicolon();
        return new BreakStmt($kw->line, $kw->column, $level);
    }

    private function parseContinue(): ContinueStmt
    {
        $kw = $this->advance();
        $level = null;
        if ($this->check(TokenType::IntLiteral)) {
            $level = (int) $this->advance()->value;
        }
        $this->expectSemicolon();
        return new ContinueStmt($kw->line, $kw->column, $level);
    }

    private function parseIf(): IfStmt
    {
        $kw = $this->advance();

        $this->expect(TokenType::LParen, "'(' attendu après 'if'");
        $cond = $this->parseExpression();
        $this->expect(TokenType::RParen, "')' attendu");
        $then = $this->parseStatement();

        $elseifs = [];
        $else = null;

        while ($this->check(TokenType::KwElseIf)) {
            $eif = $this->advance();
            $this->expect(TokenType::LParen, "'(' attendu après 'elseif'");
            $ec = $this->parseExpression();
            $this->expect(TokenType::RParen, "')' attendu");
            $eb = $this->parseStatement();
            $elseifs[] = ['cond' => $ec, 'body' => $eb];
        }

        if ($this->check(TokenType::KwElse)) {
            $this->advance();
            // "else if" (deux mots) vs "else" simple
            if ($this->check(TokenType::KwIf)) {
                // On le traite comme un elseif
                $else = $this->parseIf();
            } else {
                $else = $this->parseStatement();
            }
        }

        return new IfStmt($kw->line, $kw->column, $cond, $then, $elseifs, $else);
    }

    private function parseWhile(): WhileStmt
    {
        $kw = $this->advance();
        $this->expect(TokenType::LParen, "'(' attendu après 'while'");
        $cond = $this->parseExpression();
        $this->expect(TokenType::RParen, "')' attendu");
        $body = $this->parseStatement();
        return new WhileStmt($kw->line, $kw->column, $cond, $body);
    }

        private function parseFor(): ForStmt
    {
        $kw = $this->advance();
        $this->expect(TokenType::LParen, "'(' attendu après 'for'");

        // Init : soit une déclaration typée (int $i = 0), soit des expressions
        $initDecl = null;
        $init = [];

        if ($this->looksLikeTypedDeclaration()) {
            $initDecl = $this->parseVarDecl();
            // parseVarDecl consomme le ';' final
        } else {
            $init = $this->parseExpressionList(TokenType::Semicolon);
            $this->expect(TokenType::Semicolon, "';' attendu dans 'for'");
        }

        $cond = $this->parseExpressionList(TokenType::Semicolon);
        $this->expect(TokenType::Semicolon, "';' attendu dans 'for'");
        $step = $this->parseExpressionList(TokenType::RParen);
        $this->expect(TokenType::RParen, "')' attendu dans 'for'");

        $body = $this->parseStatement();
        return new ForStmt($kw->line, $kw->column, $init, $cond, $step, $body, $initDecl);
    }

    /**
     * Liste d'expressions séparées par des virgules, terminée par $until.
     * @return Expr[]
     */
    private function parseExpressionList(TokenType $until): array
    {
        $list = [];
        if ($this->check($until)) {
            return $list;
        }
        $list[] = $this->parseExpression();
        while ($this->check(TokenType::Comma)) {
            $this->advance();
            $list[] = $this->parseExpression();
        }
        return $list;
    }

/*     private function parseForeach(): ForeachStmt
{
    $kw = $this->advance();
    $this->expect(TokenType::LParen, "'(' attendu après 'foreach'");
    $iterable = $this->parseExpression();
    $this->expect(TokenType::KwAs, "'as' attendu dans 'foreach'");

    // Premier élément : type + variable (clé ou valeur)
    $first = $this->parseForeachBinding();
    $key = null;
    $value = $first;

    if ($this->check(TokenType::DoubleArrow)) {
        $this->advance();
        $key = $first;
        $value = $this->parseForeachBinding();
    }

    $this->expect(TokenType::RParen, "')' attendu dans 'foreach'");
    $body = $this->parseStatement();
    return new ForeachStmt($kw->line, $kw->column, $iterable, $value, $key, $body);
} */




    private function parseForeach(): ForeachStmt
{
    $kw = $this->advance();
    $this->expect(TokenType::LParen, "'(' attendu après 'foreach'");
    $iterable = $this->parseExpression();
    $this->expect(TokenType::KwAs, "'as' attendu dans 'foreach'");

    // Premier binding : type + variable
    [$type1, $var1] = $this->parseForeachBinding();

    $key = null;
    $keyType = null;
    $value = $var1;
    $valueType = $type1;

    if ($this->check(TokenType::DoubleArrow)) {
        $this->advance();
        // Le premier binding était la clé
        $key = $var1;
        $keyType = $type1;

        // Deuxième binding : la valeur
        [$type2, $var2] = $this->parseForeachBinding();
        $value = $var2;
        $valueType = $type2;
    }

    $this->expect(TokenType::RParen, "')' attendu dans 'foreach'");
    $body = $this->parseStatement();

    return new ForeachStmt(
        $kw->line,
        $kw->column,
        $iterable,
        $value,
        $key,
        $body,
        $valueType,
        $keyType,
    );
}

/**
 * Parse un binding de foreach : TYPE $variable.
 *
 * @return array{TypeNode, Expr}  le type et l'expression de variable
 */
private function parseForeachBinding(): array
{
    if (!$this->canStartType($this->current())) {
        $this->error("Type obligatoire dans 'foreach'");
    }
    $type = $this->parseType();
    $var = $this->expect(TokenType::Variable, "Nom de variable attendu dans 'foreach'");
    $expr = new VariableExpr($var->line, $var->column, (string) $var->value);
    return [$type, $expr];
}





















/**
 * Parse une liaison foreach : TYPE $var
 */
 

/* 
$tab = ['hrt', 1, 'Msd', 4, true, 12, 'F', ...];

$pairs = 0;
 

 foreach($tab as int $number % 2 === 0){

    $pairs++;
 }
 

*/









    private function parseExpressionStatement(): ExprStmt
    {
        $expr = $this->parseExpression();
        $this->expectSemicolon();
        return new ExprStmt($expr->line(), $expr->column(), $expr);
    }

    // =========================================================================
    //  Expressions
    // =========================================================================

    private function parseExpression(): Expr
    {
        return $this->parseAssignment();
    }

    private function parseAssignment(): Expr
    {
        $left = $this->parseTernary();

        static $assignOps = [
            TokenType::Assign,
            TokenType::PlusAssign,
            TokenType::MinusAssign,
            TokenType::StarAssign,
            TokenType::SlashAssign,
            TokenType::PercentAssign,
            TokenType::DotAssign,
            TokenType::PowerAssign,
            TokenType::AndAssign,
            TokenType::OrAssign,
            TokenType::XorAssign,
            TokenType::ShlAssign,
            TokenType::ShrAssign,
            TokenType::CoalesceAssign,
        ];

        foreach ($assignOps as $op) {
            if ($this->check($op)) {
                $tok = $this->advance();
                $right = $this->parseAssignment(); // associatif à droite
                return new AssignExpr($tok->line, $tok->column, $tok->lexeme, $left, $right);
            }
        }

        return $left;
    }

    private function parseTernary(): Expr
    {
        $cond = $this->parseBinary(0);

        if ($this->check(TokenType::Question)) {
            $q = $this->advance();
            if ($this->check(TokenType::Colon)) {
                // ?:
                $this->advance();
                $else = $this->parseAssignment();
                return new TernaryExpr($q->line, $q->column, $cond, null, $else);
            }
            $then = $this->parseExpression();
            $this->expect(TokenType::Colon, "':' attendu dans le ternaire");
            $else = $this->parseAssignment();
            return new TernaryExpr($q->line, $q->column, $cond, $then, $else);
        }

        return $cond;
    }

    /**
     * Parse une expression binaire avec précédence (algorithme de
     * precedence climbing).
     */
    private function parseBinary(int $minPrec): Expr
    {
        $left = $this->parseUnary();

        while (true) {

            if ($this->check(TokenType::KwInstanceof)) {
                $tok = $this->advance();
                $className = $this->parseQualifiedName();
                $left = new InstanceofExpr($tok->line, $tok->column, $left, $className);
                continue;
            }

            $op = $this->binaryOp();
            if ($op === null) {
                break;
            }
            $prec = self::PRECEDENCE[$op];
            if ($prec['left'] < $minPrec) {
                break;
            }

            $tok = $this->advance();

            // Gestion spéciale du coalesce (droite associative)
            $nextMin = $prec['right'];

            // Gestion spéciale du '.' : concaténation si espace autour
            if ($tok->type === TokenType::Dot) {
                // Si espace avant ou après → concaténation définitive
                if ($tok->precededByWhitespace || $tok->followedByWhitespace) {
                    $right = $this->parseBinary($nextMin);
                    $left = new BinaryExpr($tok->line, $tok->column, '.', $left, $right);
                    continue;
                }
                // Sinon → DotAccess ou DotMethodCall (ambigu)
                $member = $this->current();
                if ($member->type !== TokenType::Identifier && $member->type !== TokenType::Variable) {
                    $this->error("Identifiant ou variable attendu après '.' sans espace");
                }
                $this->advance();
                $memberName = $member->type === TokenType::Variable
                    ? (string) $member->value
                    : (string) $member->value;

                if ($this->check(TokenType::LParen)) {
                    $args = $this->parseCallArgs();
                    $left = new DotMethodCallExpr($tok->line, $tok->column, $left, $memberName, $args);
                } else {
                    $left = new DotAccessExpr($tok->line, $tok->column, $left, $memberName);
                }
                continue;
            }

            $right = $this->parseBinary($nextMin);
            $left = new BinaryExpr($tok->line, $tok->column, $tok->lexeme, $left, $right);
        }

        return $left;
    }

    /**
     * Renvoie le lexème de l'opérateur binaire courant, ou null si aucun.
     */
    private function binaryOp(): ?string
    {
        $t = $this->current()->type;
        return match ($t) {
            TokenType::Plus, TokenType::Minus, TokenType::Star, TokenType::Slash,
            TokenType::Percent, TokenType::Power,
            TokenType::Equal, TokenType::NotEqual, TokenType::Identical,
            TokenType::NotIdentical, TokenType::Less, TokenType::LessEqual,
            TokenType::Greater, TokenType::GreaterEqual, TokenType::Spaceship,
            TokenType::And, TokenType::Or, TokenType::BitAnd, TokenType::BitOr,
            TokenType::BitXor, TokenType::Shl, TokenType::Shr,
            TokenType::NullCoalesce, TokenType::Dot
                => $this->current()->lexeme,
            default => null,
        };
    }

    private function parseUnary(): Expr
    {
        $t = $this->current();

        // Unaires préfixes
        if ($t->type === TokenType::Minus || $t->type === TokenType::Plus) {
            $this->advance();
            $operand = $this->parseUnary();
            return new UnaryExpr($t->line, $t->column, $t->lexeme, $operand);
        }
        if ($t->type === TokenType::Not || $t->type === TokenType::BitNot) {
            $this->advance();
            $operand = $this->parseUnary();
            return new UnaryExpr($t->line, $t->column, $t->lexeme, $operand);
        }
        if ($t->type === TokenType::Increment || $t->type === TokenType::Decrement) {
            $this->advance();
            $operand = $this->parseUnary();
            return new PreIncDecExpr($t->line, $t->column, $t->lexeme, $operand);
        }
        if ($t->type === TokenType::KwNew) {
            return $this->parseNew();
        }

        return $this->parsePostfix();
    }

    private function parsePostfix(): Expr
    {
        $expr = $this->parsePrimary();

        while (true) {
            $t = $this->current();

            if ($t->type === TokenType::LParen) {
                $args = $this->parseCallArgs();
                $expr = new CallExpr($t->line, $t->column, $expr, $args);
                continue;
            }
            if ($t->type === TokenType::LBracket) {
                $this->advance();
                $index = null;
                if (!$this->check(TokenType::RBracket)) {
                    $index = $this->parseExpression();
                }
                $this->expect(TokenType::RBracket, "']' attendu");
                $expr = new IndexExpr($t->line, $t->column, $expr, $index);
                continue;
            }
            if ($t->type === TokenType::Arrow) {
                $this->advance();
                $member = $this->current();
                if ($member->type !== TokenType::Identifier && $member->type !== TokenType::Variable) {
                    $this->error("Identifiant attendu après '->'");
                }
                $this->advance();
                $name = (string) $member->value;
                if ($this->check(TokenType::LParen)) {
                    $args = $this->parseCallArgs();
                    $expr = new MethodCallExpr($t->line, $t->column, $expr, $name, $args);
                } else {
                    $expr = new PropertyAccessExpr($t->line, $t->column, $expr, $name);
                }
                continue;
            }
            if ($t->type === TokenType::Increment || $t->type === TokenType::Decrement) {
                $this->advance();
                $expr = new PostIncDecExpr($t->line, $t->column, $t->lexeme, $expr);
                continue;
            }
            break;
        }

        return $expr;
    }

    private function parsePrimary(): Expr
    {
        $t = $this->current();
 
        if ($t->type === TokenType::Identifier) { 
            $this->advance();
            return new VariableExpr($t->line, $t->column, (string) $t->value);
        }
        if ($t->type === TokenType::KwThis) {
            $this->advance();
            return new ThisExpr($t->line, $t->column);
        }
        if ($t->type === TokenType::Variable) {
            $this->advance();
            return new VariableExpr($t->line, $t->column, (string) $t->value);
        }
        if ($t->type === TokenType::IntLiteral) {
            $this->advance();
            return new LiteralExpr($t->line, $t->column, $t->value, 'int');
        }
        if ($t->type === TokenType::FloatLiteral) {
            $this->advance();
            return new LiteralExpr($t->line, $t->column, $t->value, 'float');
        }
        if ($t->type === TokenType::StringLiteral) {
            $this->advance();
            return new LiteralExpr($t->line, $t->column, $t->value, 'string');
        }
        if ($t->type === TokenType::KwTrue || $t->type === TokenType::KwFalse) {
            $this->advance();
            return new LiteralExpr($t->line, $t->column, (bool) $t->value, 'bool');
        }
        if ($t->type === TokenType::KwNull) {
            $this->advance();
            return new LiteralExpr($t->line, $t->column, null, 'null');
        }
            if ($t->type === TokenType::LBracket) {
            return $this->parseArrayLiteral();
        }
        if ($t->type === TokenType::LParen) {
            $this->advance();
            $expr = $this->parseExpression();
            $this->expect(TokenType::RParen, "')' attendu");
            return $expr;
        }

        $this->error("Expression attendue");
    }

    /**
     * @return Expr[]
     */
    private function parseCallArgs(): array
    {
        $this->expect(TokenType::LParen, "'(' attendu");
        $args = [];
        if (!$this->check(TokenType::RParen)) {
            $args[] = $this->parseExpression();
            while ($this->check(TokenType::Comma)) {
                $this->advance();
                $args[] = $this->parseExpression();
            }
        }
        $this->expect(TokenType::RParen, "')' attendu");
        return $args;
    }

    private function parseArrayLiteral(): ArrayLiteralExpr
{
    $open = $this->expect(TokenType::LBracket, "'[' attendu");
    $elements = [];
    if (!$this->check(TokenType::RBracket)) {
        $elements[] = $this->parseArrayElement();
        while ($this->check(TokenType::Comma)) {
            $this->advance();
            if ($this->check(TokenType::RBracket)) {
                break; // trailing comma
            }
            $elements[] = $this->parseArrayElement();
        }
    }
    $this->expect(TokenType::RBracket, "']' attendu");
    return new ArrayLiteralExpr($open->line, $open->column, $elements);
}

/**
 * @return array{key: ?Expr, value: Expr}
 */
    private function parseArrayElement(): array
    {
        $first = $this->parseExpression();
        if ($this->check(TokenType::DoubleArrow)) {
            $this->advance();
            $second = $this->parseExpression();
            return ['key' => $first, 'value' => $second];
        }
        return ['key' => null, 'value' => $first];
    }

    private function parseNew(): NewExpr
    {
        $kw = $this->expect(TokenType::KwNew, "'new' attendu");
        // Nom de classe : identifiant ou backslash suivi d'identifiant(s)
        $className = $this->parseClassName();
        $args = [];
        if ($this->check(TokenType::LParen)) {
            $args = $this->parseCallArgs();
        }
        return new NewExpr($kw->line, $kw->column, $className, $args);
    }

    private function parseClassName(): string
    {
        $parts = [];
        while ($this->check(TokenType::Backslash)) {
            $this->advance();
            $parts[] = '';
        }
        if (!$this->check(TokenType::Identifier)) {
            $this->error("Nom de classe attendu");
        }
        $parts[] = (string) $this->advance()->value;
        while ($this->check(TokenType::Backslash)) {
            $this->advance();
            if (!$this->check(TokenType::Identifier)) {
                $this->error("Nom de classe attendu après '\\'");
            }
            $parts[] = (string) $this->advance()->value;
        }
        return implode('\\', $parts);
    }

    // =========================================================================
    //  Utilitaires
    // =========================================================================

    private function current(): Token
    {
        return $this->tokens[$this->pos];
    }

    private function check(TokenType $type): bool
    {
        return $this->current()->type === $type;
    }

    private function advance(): Token
    {
        $t = $this->tokens[$this->pos];
        if ($t->type !== TokenType::Eof) {
            $this->pos++;
        }
        return $t;
    }

    private function expect(TokenType $type, string $message): Token
    {
        if (!$this->check($type)) {
            $this->error($message);
        }
        return $this->advance();
    }

    private function expectSemicolon(): void
    {
        $this->expect(TokenType::Semicolon, "';' attendu");
    }

    private function error(string $message): never
    {
        $t = $this->current();
        throw new ParserError(
            $message . " (token actuel : " . $t->type->name . " '" . $t->lexeme . "')",
            $this->file,
            $t->line,
            $t->column,
        );
    }


        /**
     * Un token peut-il commencer un type ?
     */
    private function canStartType(Token $t): bool
    {
        return match ($t->type) {
            TokenType::KwInt, TokenType::KwFloat, TokenType::KwBool,
            TokenType::KwString, TokenType::KwArray, TokenType::KwObject,
            TokenType::KwMixed, TokenType::KwVoid, TokenType::KwNever,
            TokenType::KwCallable, TokenType::KwIterable,
            TokenType::KwSelf, TokenType::KwParent,
            TokenType::KwNull, TokenType::Identifier,
            TokenType::Backslash, TokenType::Question
                => true,
            default => false,
        };
    }



        /**
     * Regarde si la séquence courante ressemble à une déclaration typée :
     *   TYPE $var
     *   ?TYPE $var
     *   TYPE[] $var
     *   A|B $var
     *   \Foo\Bar $var
     *
     * Ne consomme rien. C'est un lookahead pur.
     */


    


        private function looksLikeTypedDeclaration(): bool
    {
        $save = $this->pos;
        try {
            $this->tryParseType(); // jetable
                $ok = $this->check(TokenType::Variable)
                || $this->check(TokenType::KwThis);
        } catch (ParserError) {
            $ok = false;
        }
        $this->pos = $save;
        return $ok;
    }





        /**
     * Parse un type. Lève ParserError si ce n'en est pas un.
     */
    private function parseType(): TypeNode
    {
        return $this->tryParseType();
    }

    /**
     * Parse un type et le retourne. Utilisé aussi par le lookahead.
     * Peut lever ParserError.
     */
    private function tryParseType(): TypeNode
    {
        $start = $this->current();

        $nullable = false;
        if ($this->check(TokenType::Question)) {
            $nullable = true;
            $this->advance();
        }

        $first = $this->parseAtomicType();

        // Unions : A|B|C
        $union = [];
        while ($this->check(TokenType::BitOr)) {
            $this->advance();
            $union[] = $this->parseAtomicType();
        }

        if ($nullable && !empty($union)) {
            $this->error("Le '?' nullable ne peut pas être combiné avec une union");
        }

        // Construire un TypeNode fusionné : si union, le premier est $first,
        // le reste dans $union.
        return new TypeNode(
            $start->line,
            $start->column,
            $first->name,
            $nullable || $first->nullable,
            $first->arrayDepth,
            $union,
            $first->typeArgs,
        );
    }

    /**
     * Parse un type atomique : int, Foo, \Foo\Bar, T[], Box<int> (pas encore).
     */
    private function parseAtomicType(): TypeNode
    {
        $start = $this->current();

        $name = null;

        // Type de base
        if ($this->check(TokenType::Backslash) || $this->check(TokenType::Identifier)) {
            $name = $this->parseQualifiedName();
        } else {
            // Types scalaires et intégrés : mots-clés
            $t = $this->current();
            if (!$this->isBuiltinTypeKeyword($t)) {
                $this->error("Type attendu");
            }
            $name = strtolower($t->lexeme);
            $this->advance();
        }

        // [] répétés
        $arrayDepth = 0;
        while ($this->check(TokenType::LBracket)) {
            $this->advance();
            $this->expect(TokenType::RBracket, "']' attendu dans un type tableau");
            $arrayDepth++;
        }

        // <...> génériques (parsés mais pas encore exploités)
        $typeArgs = [];
        // Pour l'instant on ne parse pas les génériques, on verra à l'étape 6.

        return new TypeNode($start->line, $start->column, $name, false, $arrayDepth, [], $typeArgs);
    }

    private function isBuiltinTypeKeyword(Token $t): bool
    {
        return in_array($t->type, [
            TokenType::KwInt, TokenType::KwFloat, TokenType::KwBool,
            TokenType::KwString, TokenType::KwArray, TokenType::KwObject,
            TokenType::KwMixed, TokenType::KwVoid, TokenType::KwNever,
            TokenType::KwCallable, TokenType::KwIterable,
            TokenType::KwSelf, TokenType::KwParent, TokenType::KwNull,
        ], true);
    }

    /**
     * Parse un nom qualifié : Foo, \Foo, Foo\Bar, \Foo\Bar\Baz
     */
    private function parseQualifiedName(): string
    {
        $parts = [];
        $leadingBackslash = false;

        if ($this->check(TokenType::Backslash)) {
            $leadingBackslash = true;
            $this->advance();
        }

        if (!$this->check(TokenType::Identifier)) {
            $this->error("Nom de type attendu");
        }
        $parts[] = (string) $this->advance()->value;

        while ($this->check(TokenType::Backslash)) {
            $this->advance();
            if (!$this->check(TokenType::Identifier)) {
                $this->error("Nom attendu après '\\'");
            }
            $parts[] = (string) $this->advance()->value;
        }

        return ($leadingBackslash ? '\\' : '') . implode('\\', $parts);
    }


        private function parseVarDecl(): VarDeclStmt
    {
        $start = $this->current();
        $type = $this->parseType();

        $declarators = [];
        $declarators[] = $this->parseDeclarator();
        while ($this->check(TokenType::Comma)) {
            $this->advance();
            $declarators[] = $this->parseDeclarator();
        }

        $this->expectSemicolon();
        return new VarDeclStmt($start->line, $start->column, $type, $declarators);
    }

    /**
     * @return array{name: string, init: ?Expr, line: int, column: int}
     */
        private function parseDeclarator(): array
    {
        if ($this->check(TokenType::KwThis)) {
            $this->error("'\$this' est réservé et ne peut pas être déclaré");
        }
        $var = $this->expect(TokenType::Variable, "Nom de variable attendu dans la déclaration");

        $init = null;
        if ($this->check(TokenType::Assign)) {
            $this->advance();
            $init = $this->parseExpression();
        }

        return [
            'name' => (string) $var->value,
            'init' => $init,
            'line' => $var->line,
            'column' => $var->column,
        ];
    }

        /**
     * @return Param[]
     */
    private function parseParams(): array
    {
        $this->expect(TokenType::LParen, "'(' attendu");
        $params = [];

        if (!$this->check(TokenType::RParen)) {
            $params[] = $this->parseParam();
            while ($this->check(TokenType::Comma)) {
                $this->advance();
                $params[] = $this->parseParam();
            }
        }

        $this->expect(TokenType::RParen, "')' attendu");
        return $params;
    }

    private function parseParam(): Param
    {
        $start = $this->current();

        // Type obligatoire
        if (!$this->canStartType($start)) {
            $this->error("Type de paramètre obligatoire");
        }
        $type = $this->parseType();

        // byRef et variadic
        $byRef = false;
        $variadic = false;

        if ($this->check(TokenType::BitAnd)) {
            $byRef = true;
            $this->advance();
        }
        if ($this->check(TokenType::Ellipsis)) {
            $variadic = true;
            $this->advance();
        }

       if ($this->check(TokenType::KwThis)) {
            $this->error("'\$this' est réservé et ne peut pas être un paramètre");
        }
        $var = $this->expect(TokenType::Variable, "Nom de paramètre attendu");

        $default = null;
        if ($this->check(TokenType::Assign)) {
            $this->advance();
            $default = $this->parseExpression();
        }

        return new Param(
            $start->line,
            $start->column,
            $type,
            (string) $var->value,
            $default,
            $byRef,
            $variadic,
        );
    }
    
    
        private function parseReturnType(): TypeNode
    {
        $this->expect(TokenType::Colon, "':' attendu avant le type de retour");
        return $this->parseType();
    }
    
    
        private function parseFunctionDecl(): FunctionDeclStmt
    {
        $kw = $this->advance(); // function

        $name = $this->expect(TokenType::Identifier, "Nom de fonction attendu");
        $params = $this->parseParams();
        $returnType = null;
        if ($this->check(TokenType::Colon)) {
            $returnType = $this->parseReturnType();
        }

        if ($returnType === null) {
            $this->error("Type de retour obligatoire pour la fonction '{$name->value}'");
        }
        
        $body = $this->parseBlock();

        return new FunctionDeclStmt(
            $kw->line,
            $kw->column,
            [],
            (string) $name->value,
            $params,
            $returnType,
            $body,
        );
    }


        private function parseClassDecl(bool $isInterface = false): ClassDeclStmt
    {
        $kw = $this->advance(); // class ou interface

        $name = $this->expect(TokenType::Identifier, "Nom de classe attendu");

        $extends = null;
        if ($this->check(TokenType::KwExtends)) {
            $this->advance();
            $extends = $this->parseQualifiedName();
        }

        $implements = [];
        if ($this->check(TokenType::KwImplements)) {
            $this->advance();
            $implements[] = $this->parseQualifiedName();
            while ($this->check(TokenType::Comma)) {
                $this->advance();
                $implements[] = $this->parseQualifiedName();
            }
        }

        $this->expect(TokenType::LBrace, "'{' attendu dans la classe");
        $members = [];
        while (!$this->check(TokenType::RBrace) && !$this->check(TokenType::Eof)) {
            $members[] = $this->parseClassMember();
        }
        $this->expect(TokenType::RBrace, "'}' attendu");

        return new ClassDeclStmt(
            $kw->line,
            $kw->column,
            [],
            (string) $name->value,
            $extends,
            $implements,
            $members,
            $isInterface,
        );
    }

    private function parseClassMember(): Stmt
    {
        $start = $this->current();
        $modifiers = $this->parseModifiers();

        if ($this->check(TokenType::KwFunction)) {
            return $this->parseMethodDecl($modifiers, $start);
        }

        // Sinon : propriété typée
        return $this->parsePropertyDecl($modifiers, $start);
    }

    /**
     * @return string[]
     */
    private function parseModifiers(): array
    {
        $mods = [];
        while (true) {
            $t = $this->current()->type;
            $mod = match ($t) {
                TokenType::KwPublic    => 'public',
                TokenType::KwProtected => 'protected',
                TokenType::KwPrivate   => 'private',
                TokenType::KwStatic    => 'static',
                TokenType::KwAbstract  => 'abstract',
                TokenType::KwFinal     => 'final',
                TokenType::KwReadonly  => 'readonly',
                default => null,
            };
            if ($mod === null) {
                break;
            }
            if (in_array($mod, $mods, true)) {
                $this->error("Modificateur '$mod' dupliqué");
            }
            $mods[] = $mod;
            $this->advance();
        }
        return $mods;
    }

    private function parseMethodDecl(array $modifiers, Token $start): MethodDeclStmt
    {
        $this->advance(); // function

        $name = $this->expect(TokenType::Identifier, "Nom de méthode attendu");
        $params = $this->parseParams();
        $returnType = null;
if ($this->check(TokenType::Colon)) {
    $returnType = $this->parseReturnType();
}

// Type de retour obligatoire sauf pour __construct et __destruct
$methodName = (string) $name->value;
if ($returnType === null
) {
    if ($returnType === null
    && !in_array($methodName, ['__construct', '__destruct'], true)
) {
    $this->error("Type de retour obligatoire pour la méthode '{$methodName}'");
}
}

        $body = null;
        if ($this->check(TokenType::LBrace)) {
            $body = $this->parseBlock();
        } else {
            $this->expectSemicolon();
        }

        return new MethodDeclStmt(
            $start->line,
            $start->column,
            $modifiers,
            (string) $name->value,
            $params,
            $returnType,
            $body,
        );
    }

    private function parsePropertyDecl(array $modifiers, Token $start): PropertyDeclStmt
    {
        // Type obligatoire
        if (!$this->canStartType($this->current())) {
            $this->error("Type de propriété obligatoire");
        }
        $type = $this->parseType();

        $declarators = [];
        $declarators[] = $this->parseDeclarator();
        while ($this->check(TokenType::Comma)) {
            $this->advance();
            $declarators[] = $this->parseDeclarator();
        }

        $this->expectSemicolon();
        return new PropertyDeclStmt($start->line, $start->column, $modifiers, $type, $declarators);
    }




        private function errorAt(Token $t, string $message): never
    {
        throw new ParserError(
            $message . " (token : " . $t->type->name . " '" . $t->lexeme . "')",
            $this->file,
            $t->line,
            $t->column,
        );
    }

    /**
 * Parse `final class`, `abstract class`, `final interface`, etc.
 * Consomme les modificateurs, puis délègue à parseClassDecl.
 */
private function parseClassWithModifiers(): ClassDeclStmt
{
    $modifiers = [];
    while (true) {
        $t = $this->current()->type;
        $mod = match ($t) {
            TokenType::KwFinal    => 'final',
            TokenType::KwAbstract => 'abstract',
            default               => null,
        };
        if ($mod === null) {
            break;
        }
        if (in_array($mod, $modifiers, true)) {
            $this->error("Modificateur '$mod' dupliqué");
        }
        $modifiers[] = $mod;
        $this->advance();
    }

    if (!$this->check(TokenType::KwClass) && !$this->check(TokenType::KwInterface)) {
        $this->error("'class' ou 'interface' attendu après les modificateurs");
    }

    $isInterface = $this->check(TokenType::KwInterface);
    $classDecl = $this->parseClassDecl($isInterface);

    // Injecter les modificateurs dans le ClassDeclStmt
    return new ClassDeclStmt(
        $classDecl->line(),
        $classDecl->column(),
        $modifiers,
        $classDecl->name,
        $classDecl->extends,
        $classDecl->implements,
        $classDecl->members,
        $classDecl->isInterface,
    );
}
}