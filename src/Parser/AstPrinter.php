<?php

declare(strict_types=1);

namespace PPhp\Parser;

use PPhp\Parser\Node\Expr;
use PPhp\Parser\Node\Expr\Expr as NodeExpr;
use PPhp\Parser\Node\Stmt;
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
use PPhp\Parser\Node\Stmt\Stmt as NodeStmt;
use PPhp\Parser\Node\Stmt\WhileStmt;

/**
 * Affiche un AST sous forme indentée, pour debug et tests.
 */
final class AstPrinter
{
    public function print(ProgramNode $program): string
    {
        $lines = ['Program'];
        foreach ($program->statements as $stmt) {
            $this->printStmt($stmt, $lines, 1);
        }
        return implode("\n", $lines);
    }

    /** @param string[] $lines */
    private function printStmt(NodeStmt $stmt, array &$lines, int $indent): void
    {
        $pad = str_repeat('  ', $indent);

        switch (true) {
            case $stmt instanceof \PPhp\Parser\Node\Stmt\VarDeclStmt:
    $lines[] = $pad . 'VarDecl(' . $this->typeToString($stmt->type) . ')';
    foreach ($stmt->declarators as $d) {
        $lines[] = $pad . '  Declarator($' . $d['name'] . ')';
        if ($d['init'] !== null) {
            $this->printExpr($d['init'], $lines, $indent + 2);
        }
    }
    break;

            case $stmt instanceof \PPhp\Parser\Node\Stmt\FunctionDeclStmt:
                $mods = $stmt->modifiers ? implode(' ', $stmt->modifiers) . ' ' : '';
                $ret = $stmt->returnType !== null ? ': ' . $this->typeToString($stmt->returnType) : '';
                $lines[] = $pad . 'FunctionDecl(' . $mods . $stmt->name . $ret . ')';
                $lines[] = $pad . '  Params';
                foreach ($stmt->params as $p) {
                    $this->printParam($p, $lines, $indent + 2);
                }
                $lines[] = $pad . '  Body';
                if ($stmt->body !== null) {
                    $this->printStmt($stmt->body, $lines, $indent + 2);
                } else {
                    $lines[] = $pad . '    <abstract>';
                }
                break;

            case $stmt instanceof \PPhp\Parser\Node\Stmt\ClassDeclStmt:
                $kind = $stmt->isInterface ? 'InterfaceDecl' : 'ClassDecl';
                $ext = $stmt->extends ? ' extends ' . $stmt->extends : '';
                $impl = $stmt->implements ? ' implements ' . implode(', ', $stmt->implements) : '';
                $lines[] = $pad . $kind . '(' . $stmt->name . $ext . $impl . ')';
                foreach ($stmt->members as $m) {
                    $this->printStmt($m, $lines, $indent + 1);
                }
                break;

            case $stmt instanceof \PPhp\Parser\Node\Stmt\PropertyDeclStmt:
                $mods = $stmt->modifiers ? implode(' ', $stmt->modifiers) . ' ' : '';
                $lines[] = $pad . 'PropertyDecl(' . $mods . $this->typeToString($stmt->type) . ')';
                foreach ($stmt->declarators as $d) {
                    $lines[] = $pad . '  Declarator($' . $d['name'] . ')';
                    if ($d['init'] !== null) {
                        $this->printExpr($d['init'], $lines, $indent + 2);
                    }
                }
                break;

            case $stmt instanceof \PPhp\Parser\Node\Stmt\MethodDeclStmt:
                $mods = $stmt->modifiers ? implode(' ', $stmt->modifiers) . ' ' : '';
                $ret = $stmt->returnType !== null ? ': ' . $this->typeToString($stmt->returnType) : '';
                $lines[] = $pad . 'MethodDecl(' . $mods . $stmt->name . $ret . ')';
                $lines[] = $pad . '  Params';
                foreach ($stmt->params as $p) {
                    $this->printParam($p, $lines, $indent + 2);
                }
                $lines[] = $pad . '  Body';
                if ($stmt->body !== null) {
                    $this->printStmt($stmt->body, $lines, $indent + 2);
                } else {
                    $lines[] = $pad . '    <abstract>';
                }
                break;
            case $stmt instanceof EchoStmt:
                $lines[] = $pad . 'Echo';
                foreach ($stmt->expressions as $e) {
                    $this->printExpr($e, $lines, $indent + 1);
                }
                break;

            case $stmt instanceof ReturnStmt:
                $lines[] = $pad . 'Return';
                if ($stmt->value !== null) {
                    $this->printExpr($stmt->value, $lines, $indent + 1);
                }
                break;

            case $stmt instanceof BreakStmt:
                $lines[] = $pad . 'Break' . ($stmt->level !== null ? ' ' . $stmt->level : '');
                break;

            case $stmt instanceof ContinueStmt:
                $lines[] = $pad . 'Continue' . ($stmt->level !== null ? ' ' . $stmt->level : '');
                break;

            case $stmt instanceof BlockStmt:
                $lines[] = $pad . 'Block';
                foreach ($stmt->statements as $s) {
                    $this->printStmt($s, $lines, $indent + 1);
                }
                break;

            case $stmt instanceof ExprStmt:
                $lines[] = $pad . 'ExprStmt';
                $this->printExpr($stmt->expr, $lines, $indent + 1);
                break;

            case $stmt instanceof IfStmt:
                $lines[] = $pad . 'If';
                $lines[] = $pad . '  Cond';
                $this->printExpr($stmt->condition, $lines, $indent + 2);
                $lines[] = $pad . '  Then';
                $this->printStmt($stmt->then, $lines, $indent + 2);
                foreach ($stmt->elseifs as $eif) {
                    $lines[] = $pad . '  ElseIf';
                    $lines[] = $pad . '    Cond';
                    $this->printExpr($eif['cond'], $lines, $indent + 3);
                    $lines[] = $pad . '    Body';
                    $this->printStmt($eif['body'], $lines, $indent + 3);
                }
                if ($stmt->else !== null) {
                    $lines[] = $pad . '  Else';
                    $this->printStmt($stmt->else, $lines, $indent + 2);
                }
                break;

            case $stmt instanceof WhileStmt:
                $lines[] = $pad . 'While';
                $lines[] = $pad . '  Cond';
                $this->printExpr($stmt->condition, $lines, $indent + 2);
                $lines[] = $pad . '  Body';
                $this->printStmt($stmt->body, $lines, $indent + 2);
                break;

            case $stmt instanceof ForStmt:
                $lines[] = $pad . 'For';
                $lines[] = $pad . '  Init';
                if ($stmt->initDecl !== null) {
                    $this->printStmt($stmt->initDecl, $lines, $indent + 2);
                } else {
                    foreach ($stmt->init as $e) {
                        $this->printExpr($e, $lines, $indent + 2);
                    }
                }
                $lines[] = $pad . '  Cond';
                foreach ($stmt->cond as $e) {
                    $this->printExpr($e, $lines, $indent + 2);
                }
                $lines[] = $pad . '  Step';
                foreach ($stmt->step as $e) {
                    $this->printExpr($e, $lines, $indent + 2);
                }
                $lines[] = $pad . '  Body';
                $this->printStmt($stmt->body, $lines, $indent + 2);
                break;    


            case $stmt instanceof \PPhp\Parser\Node\Stmt\ForeachStmt:
                $lines[] = $pad . 'Foreach';
                $lines[] = $pad . '  Iterable';
                $this->printExpr($stmt->iterable, $lines, $indent + 2);

                if ($stmt->key !== null && $stmt->keyType !== null) {
                    $lines[] = $pad . '  Key (' . $this->typeToString($stmt->keyType) . ')';
                    $this->printExpr($stmt->key, $lines, $indent + 2);
                }
                if ($stmt->valueType !== null) {
                    $lines[] = $pad . '  Value (' . $this->typeToString($stmt->valueType) . ')';
                    $this->printExpr($stmt->value, $lines, $indent + 2);
                } else {
                    $lines[] = $pad . '  Value';
                    $this->printExpr($stmt->value, $lines, $indent + 2);
                }
                $lines[] = $pad . '  Body';
                $this->printStmt($stmt->body, $lines, $indent + 2);
                break;

            default:
                $lines[] = $pad . 'Unsupported<' . $stmt::class . '>';
        }
    }

    /** @param string[] $lines */
    private function printExpr(NodeExpr $expr, array &$lines, int $indent): void
    {
        $pad = str_repeat('  ', $indent);

        switch (true) {
                        case $expr instanceof \PPhp\Parser\Node\Expr\ThisExpr:
                $lines[] = $pad . 'This';
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\InstanceofExpr:
                $lines[] = $pad . 'Instanceof(' . $expr->className . ')';
                $this->printExpr($expr->operand, $lines, $indent + 1);
                break;
            case $expr instanceof \PPhp\Parser\Node\Expr\LiteralExpr:
                $v = var_export($expr->value, true);
                $lines[] = $pad . 'Literal(' . $expr->kind . ', ' . $v . ')';
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\VariableExpr:
                $lines[] = $pad . 'Var($' . $expr->name . ')';
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\BinaryExpr:
                $lines[] = $pad . 'Binary(' . $expr->op . ')';
                $this->printExpr($expr->left, $lines, $indent + 1);
                $this->printExpr($expr->right, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\UnaryExpr:
                $lines[] = $pad . 'Unary(' . $expr->op . ')';
                $this->printExpr($expr->operand, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\PreIncDecExpr:
                $lines[] = $pad . 'PreIncDec(' . $expr->op . ')';
                $this->printExpr($expr->operand, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\PostIncDecExpr:
                $lines[] = $pad . 'PostIncDec(' . $expr->op . ')';
                $this->printExpr($expr->operand, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\AssignExpr:
                $lines[] = $pad . 'Assign(' . $expr->op . ')';
                $this->printExpr($expr->target, $lines, $indent + 1);
                $this->printExpr($expr->value, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\TernaryExpr:
                $lines[] = $pad . 'Ternary';
                $lines[] = $pad . '  Cond';
                $this->printExpr($expr->condition, $lines, $indent + 2);
                if ($expr->then !== null) {
                    $lines[] = $pad . '  Then';
                    $this->printExpr($expr->then, $lines, $indent + 2);
                }
                $lines[] = $pad . '  Else';
                $this->printExpr($expr->else, $lines, $indent + 2);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\CoalesceExpr:
                $lines[] = $pad . 'Coalesce';
                $this->printExpr($expr->left, $lines, $indent + 1);
                $this->printExpr($expr->right, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\CallExpr:
                $lines[] = $pad . 'Call';
                $lines[] = $pad . '  Callee';
                $this->printExpr($expr->callee, $lines, $indent + 2);
                if (!empty($expr->args)) {
                    $lines[] = $pad . '  Args';
                    foreach ($expr->args as $a) {
                        $this->printExpr($a, $lines, $indent + 2);
                    }
                }
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\IndexExpr:
                $lines[] = $pad . 'Index';
                $lines[] = $pad . '  Target';
                $this->printExpr($expr->target, $lines, $indent + 2);
                if ($expr->index !== null) {
                    $lines[] = $pad . '  Index';
                    $this->printExpr($expr->index, $lines, $indent + 2);
                }
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\PropertyAccessExpr:
                $lines[] = $pad . 'PropertyAccess(->' . $expr->property . ')';
                $this->printExpr($expr->target, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\MethodCallExpr:
                $lines[] = $pad . 'MethodCall(->' . $expr->method . ')';
                $this->printExpr($expr->target, $lines, $indent + 1);
                if (!empty($expr->args)) {
                    $lines[] = $pad . '  Args';
                    foreach ($expr->args as $a) {
                        $this->printExpr($a, $lines, $indent + 2);
                    }
                }
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\DotAccessExpr:
                $lines[] = $pad . 'DotAccess(.' . $expr->member . ')';
                $this->printExpr($expr->target, $lines, $indent + 1);
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\DotMethodCallExpr:
                $lines[] = $pad . 'DotMethodCall(.' . $expr->method . ')';
                $this->printExpr($expr->target, $lines, $indent + 1);
                if (!empty($expr->args)) {
                    $lines[] = $pad . '  Args';
                    foreach ($expr->args as $a) {
                        $this->printExpr($a, $lines, $indent + 2);
                    }
                }
                break;

            case $expr instanceof \PPhp\Parser\Node\Expr\NewExpr:
                $lines[] = $pad . 'New(' . $expr->className . ')';
                if (!empty($expr->args)) {
                    $lines[] = $pad . '  Args';
                    foreach ($expr->args as $a) {
                        $this->printExpr($a, $lines, $indent + 2);
                    }
                }
                break;

            default:
                $lines[] = $pad . 'Unsupported<' . $expr::class . '>';
        }
    }

        private function typeToString(\PPhp\Parser\Node\TypeNode $t): string
    {
        $s = '';
        if ($t->nullable) {
            $s .= '?';
        }
        $s .= $t->name;
        $s .= str_repeat('[]', $t->arrayDepth);
        if (!empty($t->union)) {
            foreach ($t->union as $u) {
                $s .= '|' . $this->typeToString($u);
            }
        }
        return $s;
    }

    /** @param string[] $lines */
    private function printParam(\PPhp\Parser\Node\Param $p, array &$lines, int $indent): void
    {
        $pad = str_repeat('  ', $indent);
        $ref = $p->byRef ? '&' : '';
        $var = $p->variadic ? '...' : '';
        $def = $p->default !== null ? ' = ...' : '';
        $lines[] = $pad . 'Param(' . $this->typeToString($p->type) . ' ' . $ref . $var . '$' . $p->name . $def . ')';
        if ($p->default !== null) {
            $this->printExpr($p->default, $lines, $indent + 1);
        }
    }
}