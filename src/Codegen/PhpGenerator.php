<?php

declare(strict_types=1);

namespace PPhp\Codegen;

use PPhp\Parser\Node\Expr\ArrayLiteralExpr;
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
use PPhp\Parser\Node\Expr\ThisExpr;
use PPhp\Parser\Node\Expr\UnaryExpr;
use PPhp\Parser\Node\Expr\VariableExpr;
use PPhp\Parser\Node\Stmt\BlockStmt;
use PPhp\Parser\Node\Stmt\BreakStmt;
use PPhp\Parser\Node\Stmt\ContinueStmt;
use PPhp\Parser\Node\Stmt\EchoStmt;
use PPhp\Parser\Node\Stmt\ExprStmt;
use PPhp\Parser\Node\Stmt\ForStmt;
use PPhp\Parser\Node\Stmt\ForeachStmt;
use PPhp\Parser\Node\Stmt\IfStmt;
use PPhp\Parser\Node\Stmt\ProgramNode;
use PPhp\Parser\Node\Stmt\ReturnStmt;
use PPhp\Parser\Node\Stmt\Stmt;
use PPhp\Parser\Node\Stmt\VarDeclStmt;
use PPhp\Parser\Node\Stmt\WhileStmt;

/**
 * Génère du code PHP à partir de l'AST PPHP.
 *
 * Note : ce générateur ne supporte pour l'instant que les expressions
 * et statements simples. Les classes, fonctions, et déclarations typées
 * avancées viendront en 8b.
 */
final class PhpGenerator
{
    private string $output = '';
    private int $indentLevel = 0;
    private const INDENT = '    ';

    public function generate(ProgramNode $program): string
    {
        $this->output = "<?php\n";
        $this->indentLevel = 0;

        foreach ($program->statements as $stmt) {
            $this->genStmt($stmt);
        }

        return $this->output;
    }

    // =========================================================================
    //  Statements
    // =========================================================================

    private function genStmt(Stmt $stmt): void
    {
        match (true) {
            $stmt instanceof EchoStmt     => $this->genEcho($stmt),
            $stmt instanceof ReturnStmt   => $this->genReturn($stmt),
            $stmt instanceof BreakStmt    => $this->genBreak($stmt),
            $stmt instanceof ContinueStmt => $this->genContinue($stmt),
            $stmt instanceof IfStmt       => $this->genIf($stmt),
            $stmt instanceof WhileStmt    => $this->genWhile($stmt),
            $stmt instanceof ForStmt      => $this->genFor($stmt),
            $stmt instanceof ForeachStmt  => $this->genForeach($stmt),
            $stmt instanceof BlockStmt    => $this->genBlock($stmt),
            $stmt instanceof ExprStmt     => $this->genExprStmt($stmt),
            $stmt instanceof VarDeclStmt  => $this->genVarDecl($stmt),
            default                       => $this->genUnsupported($stmt),
        };
    }

    private function genEcho(EchoStmt $stmt): void
    {
        $parts = array_map(
            fn(Expr $e) => $this->genExpr($e),
            $stmt->expressions,
        );
        $this->writeln('echo ' . implode(', ', $parts) . ';');
    }

    private function genReturn(ReturnStmt $stmt): void
    {
        if ($stmt->value === null) {
            $this->writeln('return;');
        } else {
            $this->writeln('return ' . $this->genExpr($stmt->value) . ';');
        }
    }

    private function genBreak(BreakStmt $stmt): void
    {
        if ($stmt->level !== null) {
            $this->writeln("break {$stmt->level};");
        } else {
            $this->writeln('break;');
        }
    }

    private function genContinue(ContinueStmt $stmt): void
    {
        if ($stmt->level !== null) {
            $this->writeln("continue {$stmt->level};");
        } else {
            $this->writeln('continue;');
        }
    }

    private function genIf(IfStmt $stmt): void
    {
        $this->writeln('if (' . $this->genExpr($stmt->condition) . ') {');
        $this->indentLevel++;
        $this->genStmtBody($stmt->then);
        $this->indentLevel--;
        $this->write('}');

        foreach ($stmt->elseifs as $eif) {
            $this->write(' elseif (' . $this->genExpr($eif['cond']) . ') {');
            $this->output .= "\n";
            $this->indentLevel++;
            $this->genStmtBody($eif['body']);
            $this->indentLevel--;
            $this->write('}');
        }

        if ($stmt->else !== null) {
            $this->write(' else {');
            $this->output .= "\n";
            $this->indentLevel++;
            $this->genStmtBody($stmt->else);
            $this->indentLevel--;
            $this->write('}');
        }

        $this->output .= "\n";
    }

    private function genWhile(WhileStmt $stmt): void
    {
        $this->writeln('while (' . $this->genExpr($stmt->condition) . ') {');
        $this->indentLevel++;
        $this->genStmtBody($stmt->body);
        $this->indentLevel--;
        $this->writeln('}');
    }

        private function genFor(ForStmt $stmt): void
    {
        // Init : soit déclaration typée, soit expressions
        if ($stmt->initDecl !== null) {
            $init = $this->genVarDeclInline($stmt->initDecl);
        } else {
            $init = implode(', ', array_map(fn(Expr $e) => $this->genExpr($e), $stmt->init));
        }

        $cond = implode(', ', array_map(fn(Expr $e) => $this->genExpr($e), $stmt->cond));
        $step = implode(', ', array_map(fn(Expr $e) => $this->genExpr($e), $stmt->step));

        $this->writeln("for ({$init}; {$cond}; {$step}) {");
        $this->indentLevel++;
        $this->genStmtBody($stmt->body);
        $this->indentLevel--;
        $this->writeln('}');
    }

    /**
     * Génère une déclaration typée "inline" (sans le ';' final),
     * utilisable dans l'init d'un for.
     * Ex : int $i = 0, $j = 1; → "$i = 0, $j = 1"
     */
    private function genVarDeclInline(VarDeclStmt $stmt): string
    {
        $parts = [];
        foreach ($stmt->declarators as $d) {
            if ($d['init'] !== null) {
                $parts[] = '$' . $d['name'] . ' = ' . $this->genExpr($d['init']);
            } else {
                $parts[] = '$' . $d['name'];
            }
        }
        return implode(', ', $parts);
    }

    private function genForeach(ForeachStmt $stmt): void
    {
        $iterable = $this->genExpr($stmt->iterable);

        $header = "foreach ({$iterable} as ";
        if ($stmt->key !== null) {
            $header .= $this->genExpr($stmt->key) . ' => ';
        }
        $header .= $this->genExpr($stmt->value) . ') {';

        $this->writeln($header);
        $this->indentLevel++;
        $this->genStmtBody($stmt->body);
        $this->indentLevel--;
        $this->writeln('}');
    }

    private function genBlock(BlockStmt $stmt): void
    {
        $this->writeln('{');
        $this->indentLevel++;
        foreach ($stmt->statements as $s) {
            $this->genStmt($s);
        }
        $this->indentLevel--;
        $this->writeln('}');
    }

    private function genExprStmt(ExprStmt $stmt): void
    {
        $this->writeln($this->genExpr($stmt->expr) . ';');
    }

    private function genVarDecl(VarDeclStmt $stmt): void
    {
        // int $a = 5;      → $a = 5;
        // int $a;          → $a;     (mais PHP va warn si non initialisé)
        // int $a, $b = 2;  → $a; $b = 2;
        foreach ($stmt->declarators as $d) {
            if ($d['init'] !== null) {
                $this->writeln('$' . $d['name'] . ' = ' . $this->genExpr($d['init']) . ';');
            } else {
                $this->writeln('$' . $d['name'] . ';');
            }
        }
    }

    /**
     * Génère le contenu d'un statement "body" (qui peut être un bloc ou une seule instruction).
     */
    private function genStmtBody(Stmt $stmt): void
    {
        if ($stmt instanceof BlockStmt) {
            foreach ($stmt->statements as $s) {
                $this->genStmt($s);
            }
        } else {
            $this->genStmt($stmt);
        }
    }

    private function genUnsupported(Stmt $stmt): void
    {
        $this->writeln('/* UNSUPPORTED STMT: ' . $stmt::class . ' */');
    }

    // =========================================================================
    //  Expressions
    // =========================================================================

    private function genExpr(Expr $expr): string
    {
        return match (true) {
            $expr instanceof LiteralExpr         => $this->genLiteral($expr),
            $expr instanceof VariableExpr        => '$' . $expr->name,
            $expr instanceof ThisExpr            => '$this',
            $expr instanceof BinaryExpr          => $this->genBinary($expr),
            $expr instanceof UnaryExpr           => $this->genUnary($expr),
            $expr instanceof PreIncDecExpr       => $expr->op . $this->genExpr($expr->operand),
            $expr instanceof PostIncDecExpr      => $this->genExpr($expr->operand) . $expr->op,
            $expr instanceof AssignExpr          => $this->genAssign($expr),
            $expr instanceof TernaryExpr         => $this->genTernary($expr),
            $expr instanceof CoalesceExpr        => '(' . $this->genExpr($expr->left) . ' ?? ' . $this->genExpr($expr->right) . ')',
            $expr instanceof CallExpr            => $this->genCall($expr),
            $expr instanceof IndexExpr           => $this->genIndex($expr),
            $expr instanceof PropertyAccessExpr  => $this->genExpr($expr->target) . '->' . $expr->property,
            $expr instanceof MethodCallExpr      => $this->genMethodCall($expr),
            $expr instanceof DotAccessExpr       => $this->genDotAccess($expr),
            $expr instanceof DotMethodCallExpr   => $this->genDotMethodCall($expr),
            $expr instanceof NewExpr             => $this->genNew($expr),
            $expr instanceof InstanceofExpr      => '(' . $this->genExpr($expr->operand) . ' instanceof ' . $expr->className . ')',
            $expr instanceof ArrayLiteralExpr    => $this->genArrayLiteral($expr),
            default                              => '/* UNSUPPORTED EXPR: ' . $expr::class . ' */',
        };
    }

    private function genLiteral(LiteralExpr $expr): string
    {
        return match ($expr->kind) {
            'int'    => (string) $expr->value,
            'float'  => $this->genFloat($expr->value),
            'string' => $expr->value === '' ? "''" : $this->genString($expr),
            'bool'   => $expr->value ? 'true' : 'false',
            'null'   => 'null',
            default  => var_export($expr->value, true),
        };
    }

    private function genFloat(float $v): string
    {
        // PHP formate 3.14 en "3.14", 1e10 en "1.0E+10". On veut un truc stable.
        if (is_nan($v)) return 'NAN';
        if (is_infinite($v)) return $v > 0 ? 'INF' : '-INF';
        $s = (string) $v;
        // S'assurer qu'il y a un point ou un exposant
        if (!str_contains($s, '.') && !str_contains($s, 'e') && !str_contains($s, 'E')) {
            $s .= '.0';
        }
        return $s;
    }

    private function genString(LiteralExpr $expr): string
    {
        // On ré-émet le lexème brut. $expr->value contient le contenu sans les guillemets.
        // On ne sait pas si c'était simple ou double quote, mais on va utiliser
        // des guillemets simples + échappement PHP.
        $raw = $expr->value;
        // Échapper les apostrophes et backslashes pour une chaîne simple
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $raw);
        return "'" . $escaped . "'";
    }

    private function genBinary(BinaryExpr $expr): string
    {
        // Cas spécial : la concaténation '.' garde son opérateur.
        // Les autres opérateurs binaires sont standards.
        $left = $this->genExpr($expr->left);
        $right = $this->genExpr($expr->right);

        // Priorité : mettre des parenthèses autour pour être sûr
        return '(' . $left . ' ' . $expr->op . ' ' . $right . ')';
    }

    private function genUnary(UnaryExpr $expr): string
    {
        $operand = $this->genExpr($expr->operand);
        return $expr->op . $operand;
    }

    private function genAssign(AssignExpr $expr): string
    {
        return $this->genExpr($expr->target) . ' ' . $expr->op . ' ' . $this->genExpr($expr->value);
    }

    private function genTernary(TernaryExpr $expr): string
    {
        $cond = $this->genExpr($expr->condition);
        $else = $this->genExpr($expr->else);

        if ($expr->then === null) {
            return '(' . $cond . ' ?: ' . $else . ')';
        }
        return '(' . $cond . ' ? ' . $this->genExpr($expr->then) . ' : ' . $else . ')';
    }

    private function genCall(CallExpr $expr): string
    {
        $callee = $this->genExpr($expr->callee);
        $args = array_map(fn(Expr $a) => $this->genExpr($a), $expr->args);
        return $callee . '(' . implode(', ', $args) . ')';
    }

    private function genIndex(IndexExpr $expr): string
    {
        $target = $this->genExpr($expr->target);
        if ($expr->index === null) {
            return $target . '[]';
        }
        return $target . '[' . $this->genExpr($expr->index) . ']';
    }

    private function genMethodCall(MethodCallExpr $expr): string
    {
        $target = $this->genExpr($expr->target);
        $args = array_map(fn(Expr $a) => $this->genExpr($a), $expr->args);
        return $target . '->' . $expr->method . '(' . implode(', ', $args) . ')';
    }

    private function genDotAccess(DotAccessExpr $expr): string
    {
        // Accès via '.' → toujours un accès propriété en PHP
        $target = $this->genExpr($expr->target);
        return $target . '->' . $expr->member;
    }

    private function genDotMethodCall(DotMethodCallExpr $expr): string
    {
        $target = $this->genExpr($expr->target);
        $args = array_map(fn(Expr $a) => $this->genExpr($a), $expr->args);
        return $target . '->' . $expr->method . '(' . implode(', ', $args) . ')';
    }

    private function genNew(NewExpr $expr): string
    {
        $args = array_map(fn(Expr $a) => $this->genExpr($a), $expr->args);
        return 'new ' . $expr->className . '(' . implode(', ', $args) . ')';
    }

    private function genArrayLiteral(ArrayLiteralExpr $expr): string
    {
        if (empty($expr->elements)) {
            return '[]';
        }
        $parts = [];
        foreach ($expr->elements as $el) {
            if ($el['key'] !== null) {
                $parts[] = $this->genExpr($el['key']) . ' => ' . $this->genExpr($el['value']);
            } else {
                $parts[] = $this->genExpr($el['value']);
            }
        }
        return '[' . implode(', ', $parts) . ']';
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function write(string $s): void
    {
        $this->output .= $s;
    }

    private function writeln(string $s): void
    {
        $this->output .= str_repeat(self::INDENT, $this->indentLevel) . $s . "\n";
    }
}