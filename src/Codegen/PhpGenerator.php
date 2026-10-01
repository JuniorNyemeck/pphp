<?php

declare(strict_types=1);

namespace PPhp\Codegen;

use PPhp\Parser\Node\Expr\ArrayLiteralExpr;
use PPhp\Parser\Node\Expr\AssignExpr;
use PPhp\Parser\Node\Expr\BinaryExpr;
use PPhp\Parser\Node\Expr\CallExpr;
use PPhp\Parser\Node\Expr\CoalesceExpr;
use PPhp\Parser\Node\Expr\ConstExpr;
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
use PPhp\Runtime\SourceMapBuilder;

/**
 * Génère du code PHP à partir de l'AST PPHP.
 */
final class PhpGenerator
{
    private string $output = '';
    private int $indentLevel = 0;
    private const INDENT = '    ';

        private ?SourceMapBuilder $sourceMapBuilder = null;

    private ?MethodRegistry $registry = null;
private array $resolvedNames = [];

        public function generate(
        ProgramNode $program,
        ?MethodRegistry $registry = null,
        array $resolvedNames = [],
        ?SourceMapBuilder $sourceMapBuilder = null,
    ): string {
        $this->registry = $registry;
        $this->resolvedNames = $resolvedNames;
        $this->sourceMapBuilder = $sourceMapBuilder;

        $this->output = '';
        $this->indentLevel = 0;

        // La ligne du <?php est mappée à la ligne 1 du source
        if ($this->sourceMapBuilder !== null) {
            $this->sourceMapBuilder->startStatement(1);
        }
        $this->output .= "<?php\n";
        if ($this->sourceMapBuilder !== null) {
            $this->sourceMapBuilder->markLine();
        }

        // Compteur pour les surcharges de fonctions top-level
        $funcCounters = [];

        foreach ($program->statements as $stmt) {
            if ($this->sourceMapBuilder !== null) {
                $this->sourceMapBuilder->startStatement($stmt->line());
            }
            if ($stmt instanceof FunctionDeclStmt) {
                $name = $stmt->name;
                $index = $funcCounters[$name] ?? 0;
                $funcCounters[$name] = $index + 1;
                $this->genFunctionDecl($stmt, $index);
            } else {
                $this->genStmt($stmt);
            }
        }

        return $this->output;
    }

    // =========================================================================
    //  Statements
    // =========================================================================

    private function genStmt(Stmt $stmt, ?string $className = null): void
    {
        $this->sourceMapBuilder?->startStatement($stmt->line());

        match (true) {
            $stmt instanceof EchoStmt          => $this->genEcho($stmt),
            $stmt instanceof ReturnStmt        => $this->genReturn($stmt),
            $stmt instanceof BreakStmt         => $this->genBreak($stmt),
            $stmt instanceof ContinueStmt      => $this->genContinue($stmt),
            $stmt instanceof IfStmt            => $this->genIf($stmt),
            $stmt instanceof WhileStmt         => $this->genWhile($stmt),
            $stmt instanceof ForStmt           => $this->genFor($stmt),
            $stmt instanceof ForeachStmt       => $this->genForeach($stmt),
            $stmt instanceof BlockStmt         => $this->genBlock($stmt),
            $stmt instanceof ExprStmt          => $this->genExprStmt($stmt),
            $stmt instanceof VarDeclStmt       => $this->genVarDecl($stmt),
            $stmt instanceof FunctionDeclStmt  => $this->genFunctionDecl($stmt, 0),
            $stmt instanceof ClassDeclStmt     => $this->genClassDecl($stmt),
            $stmt instanceof PropertyDeclStmt  => $this->genPropertyDecl($stmt),
            $stmt instanceof MethodDeclStmt    => $this->genMethodDecl($stmt, $className, 0),
            default                            => $this->genUnsupported($stmt),
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
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->writeln('if (' . $this->genExpr($stmt->condition) . ') {');
        $this->indentLevel++;
        $this->genStmtBody($stmt->then);
        $this->indentLevel--;
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->write('}');

        foreach ($stmt->elseifs as $eif) {
            $this->sourceMapBuilder?->startStatement($stmt->line());
            $this->write(' elseif (' . $this->genExpr($eif['cond']) . ') {');
            $this->output .= "\n";
            $this->sourceMapBuilder?->markLine();
            $this->indentLevel++;
            $this->genStmtBody($eif['body']);
            $this->indentLevel--;
            $this->sourceMapBuilder?->startStatement($stmt->line());
            $this->write('}');
        }

        if ($stmt->else !== null) {
            $this->sourceMapBuilder?->startStatement($stmt->line());
            $this->write(' else {');
            $this->output .= "\n";
            $this->sourceMapBuilder?->markLine();
            $this->indentLevel++;
            $this->genStmtBody($stmt->else);
            $this->indentLevel--;
            $this->sourceMapBuilder?->startStatement($stmt->line());
            $this->write('}');
        }

        $this->output .= "\n";
        $this->sourceMapBuilder?->markLine();
    }

        private function genWhile(WhileStmt $stmt): void
    {
        $this->writeln('while (' . $this->genExpr($stmt->condition) . ') {');
        $this->indentLevel++;
        $this->genStmtBody($stmt->body);
        $this->indentLevel--;
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->writeln('}');
    }

    private function genFor(ForStmt $stmt): void
    {
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
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->writeln('}');
    }
    

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
        $this->sourceMapBuilder?->startStatement($stmt->line());
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
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->writeln('}');
    }

    private function genExprStmt(ExprStmt $stmt): void
    {
        $this->writeln($this->genExpr($stmt->expr) . ';');
    }

    private function genVarDecl(VarDeclStmt $stmt): void
    {
        foreach ($stmt->declarators as $d) {
            if ($d['init'] !== null) {
                $this->writeln('$' . $d['name'] . ' = ' . $this->genExpr($d['init']) . ';');
            } else {
                $this->writeln('$' . $d['name'] . ';');
            }
        }
    }

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
            $expr instanceof ConstExpr => $expr->name,
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
        if (is_nan($v)) return 'NAN';
        if (is_infinite($v)) return $v > 0 ? 'INF' : '-INF';
        $s = (string) $v;
        if (!str_contains($s, '.') && !str_contains($s, 'e') && !str_contains($s, 'E')) {
            $s .= '.0';
        }
        return $s;
    }

    private function genString(LiteralExpr $expr): string
    {
        $raw = $expr->value;
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $raw);
        return "'" . $escaped . "'";
    }

    private function genBinary(BinaryExpr $expr): string
    {
        $left = $this->genExpr($expr->left);
        $right = $this->genExpr($expr->right);
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
        // Si l'appel a été résolu et que le callee est une VariableExpr,
        // on remplace son nom.
        if ($expr->callee instanceof VariableExpr) {
            $resolved = $this->resolvedNameOf(
                $expr->line(),
                $expr->column(),
                $expr->callee->name,
            );
            $callee = $resolved;
        }
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
        $method = $this->resolvedNameOf(
            $expr->line(),
            $expr->column(),
            $expr->method,
        );
        $args = array_map(fn(Expr $a) => $this->genExpr($a), $expr->args);
        return $target . '->' . $method . '(' . implode(', ', $args) . ')';
    }

    private function genDotAccess(DotAccessExpr $expr): string
    {
        $target = $this->genExpr($expr->target);
        return $target . '->' . $expr->member;
    }

        private function genDotMethodCall(DotMethodCallExpr $expr): string
    {
        $target = $this->genExpr($expr->target);
        $method = $this->resolvedNameOf(
            $expr->line(),
            $expr->column(),
            $expr->method,
        );
        $args = array_map(fn(Expr $a) => $this->genExpr($a), $expr->args);
        return $target . '->' . $method . '(' . implode(', ', $args) . ')';
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
    //  Fonctions et classes
    // =========================================================================

        private function genFunctionDecl(FunctionDeclStmt $stmt, int $overloadIndex = 0): void
    {
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $mods = $stmt->modifiers !== [] ? implode(' ', $stmt->modifiers) . ' ' : '';
        $name = $this->registry?->functionName($stmt->name, $overloadIndex)
            ?? $stmt->name;
        $params = $this->genParams($stmt->params);
        $return = $stmt->returnType !== null ? ': ' . $this->genType($stmt->returnType) : '';

        $this->writeln("{$mods}function {$name}({$params}){$return} {");
        $this->indentLevel++;

                if ($stmt->body !== null) {
            foreach ($stmt->body->statements as $s) {
                $this->genStmt($s);
            }
        }

        $this->indentLevel--;
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->writeln('}');
    }

        private function genClassDecl(ClassDeclStmt $stmt): void
    {
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $kind = $stmt->isInterface ? 'interface' : 'class';
        $mods = $stmt->modifiers !== [] ? implode(' ', $stmt->modifiers) . ' ' : '';
        $name = $stmt->name;
        $extends = $stmt->extends !== null ? ' extends ' . $stmt->extends : '';
        $implements = $stmt->implements !== []
            ? ' implements ' . implode(', ', $stmt->implements)
            : '';

        $this->writeln("{$mods}{$kind} {$name}{$extends}{$implements} {");
        $this->indentLevel++;

        // Dispatcher de constructeur si surchargé
        if ($this->registry !== null
            && $this->registry->hasConstructorDispatcher($stmt->name)
        ) {
            $this->genConstructorDispatcher($stmt->name);
        }

        // Compteur pour les surcharges de méthodes
                $methodCounters = [];
        foreach ($stmt->members as $member) {
            if ($member instanceof MethodDeclStmt) {
                $memberName = $member->name;
                $index = $methodCounters[$memberName] ?? 0;
                $methodCounters[$memberName] = $index + 1;
                $this->genMethodDecl($member, $stmt->name, $index);
            } else {
                $this->genStmt($member, $stmt->name);
            }
        }
        $this->indentLevel--;
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->writeln('}');
    }

        private function genPropertyDecl(PropertyDeclStmt $stmt): void
    {
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $mods = $stmt->modifiers !== [] ? implode(' ', $stmt->modifiers) . ' ' : '';
        $type = $this->genType($stmt->type) . ' ';

        foreach ($stmt->declarators as $d) {
            if ($d['init'] !== null) {
                $this->writeln("{$mods}{$type}\${$d['name']} = " . $this->genExpr($d['init']) . ';');
            } else {
                $this->writeln("{$mods}{$type}\${$d['name']};");
            }
        }
    }

     private function genMethodDecl(
        MethodDeclStmt $stmt,
        ?string $className = null,
        int $overloadIndex = 0,
    ): void {
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $mods = $stmt->modifiers !== [] ? implode(' ', $stmt->modifiers) . ' ' : '';
        $name = $this->registry?->methodName($className, $stmt->name, $overloadIndex)
            ?? $stmt->name;
        $params = $this->genParams($stmt->params);
        $return = $stmt->returnType !== null ? ': ' . $this->genType($stmt->returnType) : '';

                $this->write(str_repeat(self::INDENT, $this->indentLevel));
        $this->write("{$mods}function {$name}({$params}){$return}");

        if ($stmt->body === null) {
            $this->output .= ";\n";
            $this->sourceMapBuilder?->markLine();
            return;
        }

                $this->output .= " {\n";
        $this->sourceMapBuilder?->markLine();
        $this->indentLevel++;
        foreach ($stmt->body->statements as $s) {
            $this->genStmt($s, $className);
        }
        $this->indentLevel--;
        $this->sourceMapBuilder?->startStatement($stmt->line());
        $this->writeln('}');
    }

        private function genConstructorDispatcher(string $className): void
    {
        $infos = $this->registry->constructorInfos($className);
        if (empty($infos)) {
            return;
        }

        // Le dispatcher est mappé à la ligne du premier constructeur
        $this->sourceMapBuilder?->startStatement($infos[0]->line);

        // Modificateurs (visibilité + static), uniformes pour toutes les surcharges
        $mods = [];
        foreach (['public', 'protected', 'private'] as $vis) {
            if (in_array($vis, $infos[0]->modifiers, true)) {
                $mods[] = $vis;
                break;
            }
        }
        if (empty($mods)) {
            $mods[] = 'public';
        }
        if (in_array('static', $infos[0]->modifiers, true)) {
            $mods[] = 'static';
        }
        $modsStr = implode(' ', $mods) . ' ';

        $paramCount = count($infos[0]->params);

        $this->writeln("{$modsStr}function __construct(...\$args) {");
        $this->indentLevel++;

        $this->writeln('$count = count($args);');
        $this->writeln("if (\$count !== {$paramCount}) {");
        $this->indentLevel++;
        $this->writeln("throw new \\TypeError('Aucune surcharge de __construct ne correspond');");
        $this->indentLevel--;
        $this->writeln('}');

        // Un test par surcharge
        foreach ($infos as $index => $info) {
            $tests = [];
            foreach ($info->params as $i => $param) {
                $test = $this->genTypeCheck($param->type, "\$args[{$i}]");
                if ($test !== 'true') {
                    $tests[] = $test;
                }
            }

            $condition = empty($tests) ? 'true' : implode(' && ', $tests);
            $this->writeln("if ({$condition}) {");
            $this->indentLevel++;

            $mangled = $this->registry->methodName($className, '__construct', $index);

            // Construire la liste d'arguments ($args[0], $args[1], ...)
            $argList = $paramCount === 0
                ? ''
                : implode(', ', array_map(
                    fn($i) => "\$args[{$i}]",
                    range(0, $paramCount - 1),
                ));

            $this->writeln("return \$this->{$mangled}({$argList});");

            $this->indentLevel--;
            $this->writeln('}');
        }

        $this->writeln("throw new \\TypeError('Aucune surcharge de __construct ne correspond');");
        $this->indentLevel--;
        $this->sourceMapBuilder?->startStatement($infos[0]->line);
        $this->writeln('}');
    }

    /**
     * Génère une condition PHP qui vérifie que $argExpr est du type $type.
     * Retourne 'true' si le type est mixed (pas de contrainte).
     */
    private function genTypeCheck(\PPhp\Parser\Node\TypeNode $type, string $argExpr): string
    {
        // Nullable : (test(T) || arg === null)
        if ($type->nullable) {
            $inner = new \PPhp\Parser\Node\TypeNode(
                $type->line,
                $type->column,
                $type->name,
                false,
                $type->arrayDepth,
                $type->union,
            );
            $innerTest = $this->genTypeCheck($inner, $argExpr);
            if ($innerTest === 'true') {
                return 'true';
            }
            return '(' . $innerTest . ' || ' . $argExpr . ' === null)';
        }

        // Tableaux : T[] → is_array
        if ($type->arrayDepth > 0) {
            return 'is_array(' . $argExpr . ')';
        }

        // Union : (test1 || test2 || ...)
        if (!empty($type->union)) {
            $parts = [$this->genSingleTypeCheck($type->name, $argExpr)];
            foreach ($type->union as $u) {
                $parts[] = $this->genTypeCheck($u, $argExpr);
            }
            // Si un des types est mixed, tout est acceptable
            if (in_array('true', $parts, true)) {
                return 'true';
            }
            return '(' . implode(' || ', $parts) . ')';
        }

        // Simple
        return $this->genSingleTypeCheck($type->name, $argExpr);
    }

    private function genSingleTypeCheck(string $name, string $argExpr): string
    {
        return match (strtolower($name)) {
            'int'      => "is_int({$argExpr})",
            'float'    => "(is_float({$argExpr}) || is_int({$argExpr}))",
            'bool'     => "is_bool({$argExpr})",
            'string'   => "is_string({$argExpr})",
            'array'    => "is_array({$argExpr})",
            'object'   => "is_object({$argExpr})",
            'mixed'    => 'true',
            'null'     => "is_null({$argExpr})",
            'callable' => "is_callable({$argExpr})",
            'iterable' => "is_iterable({$argExpr})",
            default    => "{$argExpr} instanceof {$name}",
        };
    }

    // =========================================================================
    //  Paramètres et types
    // =========================================================================

    /**
     * @param Param[] $params
     */
    private function genParams(array $params): string
    {
        $parts = [];
        foreach ($params as $p) {
            $s = $this->genType($p->type) . ' ';
            if ($p->byRef) $s .= '&';
            if ($p->variadic) $s .= '...';
            $s .= '$' . $p->name;
            if ($p->default !== null) {
                $s .= ' = ' . $this->genExpr($p->default);
            }
            $parts[] = $s;
        }
        return implode(', ', $parts);
    }

    private function genType(TypeNode $type): string
    {
        if ($type->arrayDepth > 0) {
            return 'array';
        }

        if (!empty($type->union)) {
            $parts = [$this->genSingleType($type->name)];
            foreach ($type->union as $u) {
                $parts[] = $this->genType($u);
            }
            $base = implode('|', $parts);
            return $type->nullable ? '?' . $base : $base;
        }

        $base = $this->genSingleType($type->name);
        return $type->nullable ? '?' . $base : $base;
    }

    private function genSingleType(string $name): string
    {
        return match (strtolower($name)) {
            'int', 'float', 'bool', 'string' => strtolower($name),
            'array', 'object', 'mixed', 'void', 'never',
            'callable', 'iterable' => strtolower($name),
            'null' => 'null',
            default => $name,
        };
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
        $this->sourceMapBuilder?->markLine();
    }

        private function keyOf(int $line, int $column): string
    {
        return $line . ':' . $column;
    }

    private function resolvedNameOf(int $line, int $column, string $fallback): string
    {
        return $this->resolvedNames[$this->keyOf($line, $column)] ?? $fallback;
    }
}