<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;
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
use PPhp\Parser\Node\Stmt\ReturnStmt;
use PPhp\Parser\Node\Stmt\Stmt;
use PPhp\Parser\Node\Stmt\VarDeclStmt;
use PPhp\Parser\Node\Stmt\WhileStmt;
use PPhp\Semantic\Type\ArrayType;
use PPhp\Semantic\Type\ClassType;
use PPhp\Semantic\Type\MixedType;
use PPhp\Semantic\Type\NeverType;
use PPhp\Semantic\Type\NullType;
use PPhp\Semantic\Type\NullableType;
use PPhp\Semantic\Type\ScalarType;
use PPhp\Semantic\Type\Type;
use PPhp\Semantic\Type\TypeFactory;
use PPhp\Semantic\Type\UnionType;
use PPhp\Semantic\Type\VoidType;
use PPhp\Parser\Node\Expr\ArrayLiteralExpr;  

 
 

/**
 * Passe 2 : vérification des corps de fonctions et méthodes,
 * et des statements top-level.
 */
final class TypeChecker
{
    private readonly GlobalScope $globals;
    private readonly SubtypeChecker $subtypes;
    private readonly OperatorTypeTable $operators;
    private readonly ClassHierarchy $hierarchy;
    private readonly MemberResolver $members;
    private readonly NarrowingHelper $narrowing;
    private readonly OverloadResolver $overloads;

    private string $file = '<input>';

    public function __construct(
        GlobalScope $globals,
        ?SubtypeChecker $subtypes = null,
        ?OperatorTypeTable $operators = null,
        ?ClassHierarchy $hierarchy = null,
        ?MemberResolver $members = null,
    ) {
        $this->globals = $globals;
        $this->hierarchy = $hierarchy ?? new ClassHierarchy($globals);
        $this->subtypes = $subtypes ?? new SubtypeChecker($globals, $this->hierarchy);
        $this->operators = $operators ?? new OperatorTypeTable();
        $this->members = $members ?? new MemberResolver(
    $globals,
    $this->hierarchy,
    $this->subtypes,
);
        $this->narrowing = new NarrowingHelper();
        $this->overloads = new OverloadResolver($this->subtypes);
    }
    public function check(ProgramNode $program, string $file): void
{
    $this->file = $file;

    // Un seul scope global partagé par tous les statements top-level
    $globalScope = new Scope(null, 'global');
    $globalCtx = new Context($globalScope);

    foreach ($program->statements as $stmt) {
        match (true) {
            $stmt instanceof FunctionDeclStmt => $this->checkFunction($stmt),
            $stmt instanceof ClassDeclStmt    => $this->checkClass($stmt),
            default                            => $this->checkStatement($stmt, $globalCtx),
        };
    }
}

    // =========================================================================
    //  Top-level
    // =========================================================================

    private function checkTopLevelStatement(Stmt $stmt): void
    {
        $scope = new Scope(null, 'global');
        $ctx = new Context($scope);
        $this->checkStatement($stmt, $ctx);
    }

    // =========================================================================
    //  Fonctions
    // =========================================================================

    
    private function checkFunction(FunctionDeclStmt $stmt): void
{
    // Trouver la surcharge correspondant à cet AST
    $overloads = $this->globals->getFunctions($stmt->name);
    $info = null;
    foreach ($overloads as $o) {
        if ($o->ast === $stmt) {
            $info = $o;
            break;
        }
    }
    if ($info === null) {
        return;
    }

    $scope = new Scope(null, 'function');

    // Paramètres
    foreach ($stmt->params as $p) {
        $type = TypeFactory::fromNode($p->type);
        $scope->define(new Symbol(
            $p->name,
            SymbolKind::Parameter,
            $type,
            $p->line(),
            $p->column(),
        ));
    }

    $ctx = (new Context($scope))->withFunction($info->returnType);

    foreach ($stmt->body?->statements ?? [] as $s) {
        $this->checkStatement($s, $ctx);
    }
}

    // =========================================================================
    //  Classes
    // =========================================================================

    private function checkClass(ClassDeclStmt $stmt): void
    {
        $info = $this->globals->getClass($stmt->name);
        if ($info === null) {
            return;
        }

        foreach ($stmt->members as $member) {
            if ($member instanceof MethodDeclStmt) {
                $this->checkMethod($info, $member);
            }
            // Les propriétés sont déjà collectées.
        }
    }

        private function checkMethod(ClassInfo $class, MethodDeclStmt $stmt): void
        {
            // On cherche la surcharge qui correspond à ce AST
            $overloads = $class->methods[$stmt->name] ?? [];
            $info = null;
            foreach ($overloads as $o) {
                if ($o->ast === $stmt) {
                    $info = $o;
                    break;
                }
            }
            if ($info === null) {
                $info = $overloads[0] ?? null;
            }
            if ($info === null) {
                return;
            }

                // Méthode abstraite : pas de corps à vérifier
                if ($stmt->body === null) {
                    return;
                }

                $scope = new Scope(null, 'function');

                // Paramètres
                foreach ($stmt->params as $p) {
                    $type = TypeFactory::fromNode($p->type, $class->name, $class->parent);
                    $scope->define(new Symbol(
                        $p->name,
                        SymbolKind::Parameter,
                        $type,
                        $p->line(),
                        $p->column(),
                    ));
                }

                $ctx = (new Context($scope))->withMethod($class, $info);

                foreach ($stmt->body->statements as $s) {
                    $this->checkStatement($s, $ctx);
                }
            }

    // =========================================================================
    //  Statements
    // =========================================================================

    private function checkStatement(Stmt $stmt, Context $ctx): void
    {
        match (true) {
            $stmt instanceof BlockStmt    => $this->checkBlock($stmt, $ctx),
            $stmt instanceof VarDeclStmt  => $this->checkVarDecl($stmt, $ctx),
            $stmt instanceof ExprStmt     => $this->checkExprStmt($stmt, $ctx),
            $stmt instanceof EchoStmt     => $this->checkEcho($stmt, $ctx),
            $stmt instanceof ReturnStmt   => $this->checkReturn($stmt, $ctx),
            $stmt instanceof IfStmt       => $this->checkIf($stmt, $ctx),
            $stmt instanceof WhileStmt    => $this->checkWhile($stmt, $ctx),
            $stmt instanceof BreakStmt    => $this->checkBreak($stmt, $ctx),
            $stmt instanceof ContinueStmt => $this->checkContinue($stmt, $ctx),
            $stmt instanceof ForStmt      => $this->checkFor($stmt, $ctx),
            $stmt instanceof ForeachStmt  => $this->checkForeach($stmt, $ctx),
            default                       => null,
        };
    }

    private function checkBlock(BlockStmt $stmt, Context $ctx): void
    {
        // Nouveau scope pour le bloc
        $scope = new Scope($ctx->scope, 'block');
        $innerCtx = $ctx->withScope($scope);
        foreach ($stmt->statements as $s) {
            $this->checkStatement($s, $innerCtx);
        }
    }

    private function checkVarDecl(VarDeclStmt $stmt, Context $ctx): void
{
    $declared = TypeFactory::fromNode(
        $stmt->type,
        $ctx->currentClass?->name,
        $ctx->currentClass?->parent,
    );

    // Vérifier que le type est valide (classe existante, etc.)
    $this->validateType($declared, $stmt->line(), $stmt->column());

    foreach ($stmt->declarators as $d) {
        $name = $d['name'];

        // Enregistrer le symbole dans le scope courant
        $ctx->scope->define(new Symbol(
            $name,
            SymbolKind::Variable,
            $declared,
            $d['line'],
            $d['column'],
        ));

        if ($d['init'] === null) {
            continue;
        }

        $initType = $this->checkExpr($d['init'], $ctx);

        // Cas spécial : un littéral de tableau vide [] est compatible
        // avec tout type tableau T[].
        $isEmptyArrayLiteral = $d['init'] instanceof ArrayLiteralExpr
            && empty($d['init']->elements);
        $isArrayTarget = $declared instanceof ArrayType;

        $compatible = ($isEmptyArrayLiteral && $isArrayTarget)
            || $this->subtypes->isSubtypeOf($initType, $declared);

        if (!$compatible) {
            throw new TypeError(
                "Type incompatible : '{$name}' attend '{$declared}', "
                . "mais reçoit '{$initType}'",
                $this->file,
                $d['init']->line(),
                $d['init']->column(),
            );
        }
    }
}
    private function checkExprStmt(ExprStmt $stmt, Context $ctx): void
    {
        $this->checkExpr($stmt->expr, $ctx);
    }

    private function checkEcho(EchoStmt $stmt, Context $ctx): void
    {
        foreach ($stmt->expressions as $e) {
            $t = $this->checkExpr($e, $ctx);
            // En PHP, echo accepte tout ce qui est stringifiable.
            // On accepte string, int, float, bool (mais pas array ni objet sans __toString).
            if ($t instanceof ArrayType) {
                throw new TypeError(
                    "Impossible d'afficher un tableau avec 'echo'",
                    $this->file,
                    $e->line(),
                    $e->column(),
                );
            }
        }
    }

    private function checkReturn(ReturnStmt $stmt, Context $ctx): void
    {
        if ($ctx->returnType === null) {
            throw new TypeError(
                "'return' ne peut être utilisé qu'à l'intérieur d'une fonction ou méthode",
                $this->file,
                $stmt->line(),
                $stmt->column(),
            );
        }

        if ($stmt->value === null) {
            // return; — n'est valide que si returnType est void
            if (!$ctx->returnType instanceof VoidType) {
                throw new TypeError(
                    "'return;' dans une fonction retournant '{$ctx->returnType}'",
                    $this->file,
                    $stmt->line(),
                    $stmt->column(),
                );
            }
            return;
        }

        // return expr;
        if ($ctx->returnType instanceof VoidType) {
            throw new TypeError(
                "Impossible de retourner une valeur dans une fonction 'void'",
                $this->file,
                $stmt->line(),
                $stmt->column(),
            );
        }

        $exprType = $this->checkExpr($stmt->value, $ctx);
        if (!$this->subtypes->isSubtypeOf($exprType, $ctx->returnType)) {
            throw new TypeError(
                "Type de retour incompatible : attendu '{$ctx->returnType}', "
                . "reçu '{$exprType}'",
                $this->file,
                $stmt->value->line(),
                $stmt->value->column(),
            );
        }
    }

    private function checkIf(IfStmt $stmt, Context $ctx): void
{
    $this->requireBool($stmt->condition, $ctx, "condition de 'if'");

    // Narrowing positif pour le then
    $thenScope = new Scope($ctx->scope, 'if-then');
    $this->applyNarrowings($thenScope, $this->narrowing->positive($stmt->condition));
    $this->checkStatement($stmt->then, $ctx->withScope($thenScope));

    // Accumule les narrowings négatifs pour les elseif
    $negativeNarrowings = $this->narrowing->negative($stmt->condition);

    foreach ($stmt->elseifs as $eif) {
        $eifScope = new Scope($ctx->scope, 'elseif');
        $this->applyNarrowings($eifScope, $negativeNarrowings);
        $eifCtx = $ctx->withScope($eifScope);

        $this->requireBool($eif['cond'], $eifCtx, "condition de 'elseif'");

        $eifThenScope = new Scope($eifCtx->scope, 'elseif-then');
        $this->applyNarrowings($eifThenScope, $this->narrowing->positive($eif['cond']));
        $this->checkStatement($eif['body'], $eifCtx->withScope($eifThenScope));

        $negativeNarrowings = $this->narrowing->merge(
            $negativeNarrowings,
            $this->narrowing->negative($eif['cond']),
        );
    }

    if ($stmt->else !== null) {
        $elseScope = new Scope($ctx->scope, 'else');
        $this->applyNarrowings($elseScope, $negativeNarrowings);
        $this->checkStatement($stmt->else, $ctx->withScope($elseScope));
    }
}

    private function checkWhile(WhileStmt $stmt, Context $ctx): void
{
    $this->requireBool($stmt->condition, $ctx, "condition de 'while'");

    $bodyScope = new Scope($ctx->scope, 'while-body');
    $this->applyNarrowings($bodyScope, $this->narrowing->positive($stmt->condition));

    $this->checkStatement($stmt->body, $ctx->withScope($bodyScope)->withLoop());
}

    private function checkBreak(BreakStmt $stmt, Context $ctx): void
    {
        if (!$ctx->inLoop()) {
            throw new TypeError(
                "'break' ne peut être utilisé qu'à l'intérieur d'une boucle",
                $this->file,
                $stmt->line(),
                $stmt->column(),
            );
        }
    }

    private function checkContinue(ContinueStmt $stmt, Context $ctx): void
    {
        if (!$ctx->inLoop()) {
            throw new TypeError(
                "'continue' ne peut être utilisé qu'à l'intérieur d'une boucle",
                $this->file,
                $stmt->line(),
                $stmt->column(),
            );
        }
    }

        private function checkFor(ForStmt $stmt, Context $ctx): void
    {
        $scope = new Scope($ctx->scope, 'for');
        $innerCtx = $ctx->withScope($scope)->withLoop();

        // Init : soit déclaration typée, soit expressions
        if ($stmt->initDecl !== null) {
            $this->checkVarDecl($stmt->initDecl, $innerCtx);
        } else {
            foreach ($stmt->init as $e) {
                $this->checkExpr($e, $innerCtx);
            }
        }

        foreach ($stmt->cond as $e) {
            $this->requireBool($e, $innerCtx, "condition de 'for'");
        }
        foreach ($stmt->step as $e) {
            $this->checkExpr($e, $innerCtx);
        }

        // Narrowing dans le corps à partir des conditions
        $bodyScope = new Scope($innerCtx->scope, 'for-body');
        foreach ($stmt->cond as $cond) {
            $this->applyNarrowings($bodyScope, $this->narrowing->positive($cond));
        }

        $this->checkStatement($stmt->body, $innerCtx->withScope($bodyScope));
    }

private function checkForeach(ForeachStmt $stmt, Context $ctx): void
{
    $iterableType = $this->checkExpr($stmt->iterable, $ctx);

    if (!$iterableType instanceof ArrayType) {
        throw new TypeError(
            "L'itéré de 'foreach' doit être un tableau, reçu '{$iterableType}'",
            $this->file,
            $stmt->iterable->line(),
            $stmt->iterable->column(),
        );
    }

    // Nouveau scope pour la boucle
    $scope = new Scope($ctx->scope, 'foreach');
    $innerCtx = $ctx->withScope($scope)->withLoop();

    // Le type de l'élément du tableau
    $elementType = $iterableType->element;

    // --- Valeur ---
    if (!$stmt->value instanceof VariableExpr) {
        throw new TypeError(
            "La valeur de 'foreach' doit être une variable",
            $this->file,
            $stmt->value->line(),
            $stmt->value->column(),
        );
    }

    if ($stmt->valueType === null) {
        throw new TypeError(
            "Type obligatoire pour la valeur de 'foreach'",
            $this->file,
            $stmt->value->line(),
            $stmt->value->column(),
        );
    }

    $declaredValueType = TypeFactory::fromNode(
        $stmt->valueType,
        $ctx->currentClass?->name,
        $ctx->currentClass?->parent,
    );
    $this->validateType($declaredValueType, $stmt->value->line(), $stmt->value->column());

    // Vérifier la compatibilité du type déclaré avec l'élément du tableau
    if (!$this->subtypes->isSubtypeOf($elementType, $declaredValueType)) {
        // Cas spécial : le tableau est mixed[] — on ne peut pas garantir
        // que tous les éléments sont du type déclaré.
        if ($elementType instanceof MixedType) {
            // Pour l'instant, on refuse : runtime checking reporté à plus tard.
            throw new TypeError(
                "Impossible de garantir que les éléments du tableau sont de type "
                . "'{$declaredValueType}' (tableau de type mixed[])",
                $this->file,
                $stmt->value->line(),
                $stmt->value->column(),
            );
        }
        throw new TypeError(
            "Le type de la valeur de 'foreach' est '{$declaredValueType}', "
            . "mais l'élément du tableau est '{$elementType}'",
            $this->file,
            $stmt->value->line(),
            $stmt->value->column(),
        );
    }

    $scope->define(new Symbol(
        $stmt->value->name,
        SymbolKind::Variable,
        $declaredValueType,
        $stmt->value->line(),
        $stmt->value->column(),
    ));

    // --- Clé (optionnelle) ---
    if ($stmt->key !== null) {
        if (!$stmt->key instanceof VariableExpr) {
            throw new TypeError(
                "La clé de 'foreach' doit être une variable",
                $this->file,
                $stmt->key->line(),
                $stmt->key->column(),
            );
        }

        if ($stmt->keyType === null) {
            throw new TypeError(
                "Type obligatoire pour la clé de 'foreach'",
                $this->file,
                $stmt->key->line(),
                $stmt->key->column(),
            );
        }

        $declaredKeyType = TypeFactory::fromNode(
            $stmt->keyType,
            $ctx->currentClass?->name,
            $ctx->currentClass?->parent,
        );
        $this->validateType($declaredKeyType, $stmt->key->line(), $stmt->key->column());

        // Une clé de tableau est toujours int|string en PHP.
        $keyOk = $declaredKeyType instanceof MixedType
            || ($declaredKeyType instanceof ScalarType
                && in_array($declaredKeyType->name, [ScalarType::INT, ScalarType::STRING], true));

        if (!$keyOk) {
            throw new TypeError(
                "Le type de la clé de 'foreach' doit être 'int' ou 'string', "
                . "reçu '{$declaredKeyType}'",
                $this->file,
                $stmt->key->line(),
                $stmt->key->column(),
            );
        }

        $scope->define(new Symbol(
            $stmt->key->name,
            SymbolKind::Variable,
            $declaredKeyType,
            $stmt->key->line(),
            $stmt->key->column(),
        ));
    }

    // Vérifier le corps de la boucle
    $this->checkStatement($stmt->body, $innerCtx);
}

    private function isValidForeachKeyTarget(Type $t): bool
    {
        // Accepté : int, string, mixed, ?T, T|U compatibles
        return $t instanceof ScalarType && in_array($t->name, [
            ScalarType::INT, ScalarType::STRING,
        ], true)
            || $t instanceof MixedType;
    }

    // =========================================================================
    //  Expressions
    // =========================================================================

    /**
     * Vérifie une expression et retourne son type.
     */
    private function checkExpr(Expr $expr, Context $ctx): Type
    {
        return match (true) {
            $expr instanceof LiteralExpr      => $this->checkLiteral($expr),
            $expr instanceof VariableExpr     => $this->checkVariable($expr, $ctx),
            $expr instanceof ThisExpr         => $this->checkThis($expr, $ctx),
            $expr instanceof BinaryExpr       => $this->checkBinary($expr, $ctx),
            $expr instanceof UnaryExpr        => $this->checkUnary($expr, $ctx),
            $expr instanceof PreIncDecExpr    => $this->checkIncDec($expr->operand, $expr->op, $ctx, $expr->line(), $expr->column()),
            $expr instanceof PostIncDecExpr   => $this->checkIncDec($expr->operand, $expr->op, $ctx, $expr->line(), $expr->column()),
            $expr instanceof AssignExpr       => $this->checkAssign($expr, $ctx),
            $expr instanceof TernaryExpr      => $this->checkTernary($expr, $ctx),
            $expr instanceof CoalesceExpr     => $this->checkCoalesce($expr, $ctx),
            $expr instanceof CallExpr         => $this->checkCall($expr, $ctx),
            $expr instanceof IndexExpr        => $this->checkIndex($expr, $ctx),
            $expr instanceof PropertyAccessExpr => $this->checkPropertyAccess($expr, $ctx),
            $expr instanceof MethodCallExpr   => $this->checkMethodCall($expr, $ctx),
            $expr instanceof DotAccessExpr    => $this->checkDotAccess($expr, $ctx),
            $expr instanceof DotMethodCallExpr => $this->checkDotMethodCall($expr, $ctx),
            $expr instanceof NewExpr          => $this->checkNew($expr, $ctx),
            $expr instanceof InstanceofExpr   => $this->checkInstanceof($expr, $ctx),
            $expr instanceof ArrayLiteralExpr => $this->checkArrayLiteral($expr, $ctx),
            default => throw new TypeError(
                "Expression non supportée : " . $expr::class,
                $this->file,
                $expr->line(),
                $expr->column(),
            ),
        };
    }

    private function checkLiteral(LiteralExpr $expr): Type
    {
        return match ($expr->kind) {
            'int'    => ScalarType::int(),
            'float'  => ScalarType::float(),
            'string' => ScalarType::string(),
            'bool'   => ScalarType::bool(),
            'null'   => new NullType(),
            default  => new MixedType(),
        };
    }

    private function checkVariable(VariableExpr $expr, Context $ctx): Type
    {
        $symbol = $ctx->scope->lookup($expr->name);
        if ($symbol === null) {
            throw new TypeError(
                "Variable '\${$expr->name}' non déclarée",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
        return $symbol->type;
    }


    private function checkArrayLiteral(ArrayLiteralExpr $expr, Context $ctx): Type
{
    if (empty($expr->elements)) {
        return new ArrayType(new MixedType());
    }

    $elementType = null;
    foreach ($expr->elements as $el) {
        if ($el['key'] !== null) {
            $keyType = $this->checkExpr($el['key'], $ctx);
            if (!$keyType instanceof ScalarType
                || !in_array($keyType->name, [ScalarType::INT, ScalarType::STRING], true)) {
                throw new TypeError(
                    "Clé de tableau doit être 'int' ou 'string', reçu '{$keyType}'",
                    $this->file,
                    $el['key']->line(),
                    $el['key']->column(),
                );
            }
        }
        $valType = $this->checkExpr($el['value'], $ctx);
        if ($elementType === null) {
            $elementType = $valType;
        } elseif (!$elementType->equals($valType)) {
            throw new TypeError(
                "Tous les éléments d'un tableau doivent avoir le même type "
                . "('{$elementType}' vs '{$valType}')",
                $this->file,
                $el['value']->line(),
                $el['value']->column(),
            );
        }
    }

    return new ArrayType($elementType);
}

    private function checkThis(ThisExpr $expr, Context $ctx): Type
    {
        if (!$ctx->canUseThis()) {
            if (!$ctx->inMethod()) {
                throw new TypeError(
                    "'\$this' ne peut être utilisé que dans une méthode non statique",
                    $this->file,
                    $expr->line(),
                    $expr->column(),
                );
            }
            throw new TypeError(
                "'\$this' ne peut être utilisé dans une méthode statique",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
        return new ClassType($ctx->currentClass->name);
    }

 private function checkBinary(BinaryExpr $expr, Context $ctx): Type
{
    // Cas spéciaux : && et || → narrow le RHS
    if ($expr->op === '&&') {
        $left = $this->checkExpr($expr->left, $ctx);
        if (!$left instanceof ScalarType || $left->name !== ScalarType::BOOL) {
            throw new TypeError(
                "Opérande gauche de '&&' doit être 'bool', reçu '{$left}'",
                $this->file,
                $expr->left->line(),
                $expr->left->column(),
            );
        }

        $rightScope = new Scope($ctx->scope, 'and-rhs');
        $this->applyNarrowings($rightScope, $this->narrowing->positive($expr->left));
        $right = $this->checkExpr($expr->right, $ctx->withScope($rightScope));

        if (!$right instanceof ScalarType || $right->name !== ScalarType::BOOL) {
            throw new TypeError(
                "Opérande droite de '&&' doit être 'bool', reçu '{$right}'",
                $this->file,
                $expr->right->line(),
                $expr->right->column(),
            );
        }
        return ScalarType::bool();
    }

    if ($expr->op === '||') {
        $left = $this->checkExpr($expr->left, $ctx);
        if (!$left instanceof ScalarType || $left->name !== ScalarType::BOOL) {
            throw new TypeError(
                "Opérande gauche de '||' doit être 'bool', reçu '{$left}'",
                $this->file,
                $expr->left->line(),
                $expr->left->column(),
            );
        }

        $rightScope = new Scope($ctx->scope, 'or-rhs');
        $this->applyNarrowings($rightScope, $this->narrowing->negative($expr->left));
        $right = $this->checkExpr($expr->right, $ctx->withScope($rightScope));

        if (!$right instanceof ScalarType || $right->name !== ScalarType::BOOL) {
            throw new TypeError(
                "Opérande droite de '||' doit être 'bool', reçu '{$right}'",
                $this->file,
                $expr->right->line(),
                $expr->right->column(),
            );
        }
        return ScalarType::bool();
    }

    // Reste inchangé (===, !==, opérateurs arithmétiques, etc.)
    $left = $this->checkExpr($expr->left, $ctx);
    $right = $this->checkExpr($expr->right, $ctx);

    if ($expr->op === '===' || $expr->op === '!==') {
        if (!$this->subtypes->isSubtypeOf($left, $right)
            && !$this->subtypes->isSubtypeOf($right, $left)
        ) {
            throw new TypeError(
                "Comparaison stricte entre types incompatibles : "
                . "'{$left}' {$expr->op} '{$right}'",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
        return ScalarType::bool();
    }

    $resultType = $this->operators->binaryResultType($expr->op, $left, $right);
    if ($resultType === null) {
        throw new TypeError(
            "Opération invalide : '{$left}' {$expr->op} '{$right}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }
    return $resultType;
}
    private function checkUnary(UnaryExpr $expr, Context $ctx): Type
    {
        $operandType = $this->checkExpr($expr->operand, $ctx);
        $resultType = $this->operators->unaryResultType($expr->op, $operandType);
        if ($resultType === null) {
            throw new TypeError(
                "Opération unaire invalide : {$expr->op}'{$operandType}'",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
        return $resultType;
    }

    private function checkIncDec(Expr $operand, string $op, Context $ctx, int $line, int $column): Type
    {
        $t = $this->checkExpr($operand, $ctx);
        $result = $this->operators->unaryResultType($op, $t);
        if ($result === null) {
            throw new TypeError(
                "Opération '{$op}' invalide sur '{$t}'",
                $this->file,
                $line,
                $column,
            );
        }
        return $result;
    }

    private function checkAssign(AssignExpr $expr, Context $ctx): Type
    {
        $targetType = $this->checkExpr($expr->target, $ctx);
        $valueType = $this->checkExpr($expr->value, $ctx);

        if ($expr->op === '=') {
            if (!$this->subtypes->isSubtypeOf($valueType, $targetType)) {
                throw new TypeError(
                    "Affectation incompatible : la cible est de type '{$targetType}', "
                    . "mais la valeur est de type '{$valueType}'",
                    $this->file,
                    $expr->line(),
                    $expr->column(),
                );
            }
            return $targetType;
        }

        // Assignations composées : +=, -=, etc.
        $baseOp = substr($expr->op, 0, -1); // '+=' → '+', '.=' → '.', '??=' → '?'
        if ($expr->op === '??=') {
            $baseOp = '??';
        }

        $resultType = $this->operators->binaryResultType($baseOp, $targetType, $valueType);
        if ($resultType === null) {
            throw new TypeError(
                "Opération composée invalide : '{$targetType}' {$expr->op} '{$valueType}'",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
        if (!$this->subtypes->isSubtypeOf($resultType, $targetType)) {
            throw new TypeError(
                "Le résultat de '{$expr->op}' est '{$resultType}', "
                . "incompatible avec la cible '{$targetType}'",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
        return $targetType;
    }

    private function checkTernary(TernaryExpr $expr, Context $ctx): Type
    {
        $this->requireBool($expr->condition, $ctx, "condition du ternaire");

        if ($expr->then === null) {
            // ?:
            return $this->checkExpr($expr->else, $ctx);
        }

        $thenType = $this->checkExpr($expr->then, $ctx);
        $elseType = $this->checkExpr($expr->else, $ctx);

        if ($thenType->equals($elseType)) {
            return $thenType;
        }
        return new UnionType([$thenType, $elseType]);
    }

    private function checkCoalesce(CoalesceExpr $expr, Context $ctx): Type
    {
        $left = $this->checkExpr($expr->left, $ctx);
        $right = $this->checkExpr($expr->right, $ctx);
        $result = $this->operators->binaryResultType('??', $left, $right);
        return $result ?? new UnionType([$left, $right]);
    }

    private function checkCall(CallExpr $expr, Context $ctx): Type
{
    if (!$expr->callee instanceof VariableExpr) {
        throw new TypeError(
            "Appel de fonction non supporté sur ce type de callee",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    $name = $expr->callee->name;
    $overloads = $this->globals->getFunctions($name);
    if (empty($overloads)) {
        throw new TypeError(
            "Fonction '{$name}' inconnue",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    $argTypes = array_map(
        fn($arg) => $this->checkExpr($arg, $ctx),
        $expr->args,
    );

    $func = $this->overloads->resolve(
        $overloads,
        $argTypes,
        $name,
        $this->file,
        $expr->line(),
        $expr->column(),
    );

    return $func->returnType ?? new MixedType();
}

    private function checkIndex(IndexExpr $expr, Context $ctx): Type
    {
        $targetType = $this->checkExpr($expr->target, $ctx);

        // Tableau
        if ($targetType instanceof ArrayType) {
            if ($expr->index !== null) {
                $indexType = $this->checkExpr($expr->index, $ctx);
                $validKey = $indexType instanceof ScalarType
                    && in_array($indexType->name, [ScalarType::INT, ScalarType::STRING], true);
                if (!$validKey && !$indexType instanceof MixedType) {
                    throw new TypeError(
                        "Index de tableau doit être 'int' ou 'string', reçu '{$indexType}'",
                        $this->file,
                        $expr->index->line(),
                        $expr->index->column(),
                    );
                }
            }
            return $targetType->element;
        }

        // String : accès à un caractère
        if ($targetType instanceof ScalarType && $targetType->name === ScalarType::STRING) {
            return ScalarType::string();
        }

        throw new TypeError(
            "Impossible d'indexer un '{$targetType}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

  private function checkPropertyAccess(PropertyAccessExpr $expr, Context $ctx): Type
{
    $targetType = $this->checkExpr($expr->target, $ctx);

    if (!$targetType instanceof ClassType) {
        throw new TypeError(
            "Impossible d'accéder à la propriété '{$expr->property}' "
            . "sur un '{$targetType}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    if (!$this->globals->classExists($targetType->name)) {
        throw new TypeError(
            "Classe '{$targetType->name}' inconnue",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    $prop = $this->members->findProperty($targetType->name, $expr->property);
    if ($prop === null) {
        throw new TypeError(
            "Propriété '{$expr->property}' inconnue dans la classe '{$targetType->name}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    if (!$this->members->canAccessProperty($prop, $ctx->currentClass)) {
        throw new TypeError(
            "Accès interdit à la propriété non publique '{$targetType->name}::\${$expr->property}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    return $prop->type;
}

  private function checkMethodCall(MethodCallExpr $expr, Context $ctx): Type
{
    $targetType = $this->checkExpr($expr->target, $ctx);

    if (!$targetType instanceof ClassType) {
        throw new TypeError(
            "Impossible d'appeler la méthode '{$expr->method}' sur un '{$targetType}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    if (!$this->globals->classExists($targetType->name)) {
        throw new TypeError(
            "Classe '{$targetType->name}' inconnue",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    $overloads = $this->members->findMethodOverloads($targetType->name, $expr->method);
    if (empty($overloads)) {
        throw new TypeError(
            "Méthode '{$expr->method}' inconnue dans la classe '{$targetType->name}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    // Visibilité : on vérifie sur la première surcharge (elles ont toutes la même visibilité)
    $first = $overloads[0];
    if (!$this->members->canAccessMethod($first, $ctx->currentClass)) {
        throw new TypeError(
            "Appel interdit à la méthode non publique '{$targetType->name}::{$expr->method}'",
            $this->file,
            $expr->line(),
            $expr->column(),
        );
    }

    // Évaluer les types des arguments
    $argTypes = array_map(
        fn($arg) => $this->checkExpr($arg, $ctx),
        $expr->args,
    );

    // Résoudre la surcharge
    $method = $this->overloads->resolve(
        $overloads,
        $argTypes,
        "{$targetType->name}::{$expr->method}",
        $this->file,
        $expr->line(),
        $expr->column(),
    );

    return $method->returnType ?? new MixedType();
}
/**
 * Trouve la classe qui déclare réellement la méthode.
 */
private function findDeclaringClass(string $className, string $methodName): ?ClassInfo
{
    $class = $this->globals->getClass($className);
    while ($class !== null) {
        if (isset($class->methods[$methodName])) {
            return $class;
        }
        $class = $class->parent !== null ? $this->globals->getClass($class->parent) : null;
    }
    return null;
}

/**
 * Trouve la classe qui déclare réellement la méthode.
 */


    private function checkDotAccess(DotAccessExpr $expr, Context $ctx): Type
    {
        // Accès '.' sans espace : doit être un PropertyAccess si l'opérande gauche est un objet.
        $targetType = $this->checkExpr($expr->target, $ctx);

        if (!$targetType instanceof ClassType) {
            throw new TypeError(
                "Accès '.' sur un '{$targetType}' : seul un objet supporte l'accès propriété. "
                . "Pour concaténer, ajoutez des espaces autour du '.'",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }

        // Reconstruire un PropertyAccess virtuel et le vérifier
        $virtual = new PropertyAccessExpr(
            $expr->line(),
            $expr->column(),
            $expr->target,
            $expr->member,
        );
        return $this->checkPropertyAccess($virtual, $ctx);
    }

    private function checkDotMethodCall(DotMethodCallExpr $expr, Context $ctx): Type
    {
        $targetType = $this->checkExpr($expr->target, $ctx);

        if (!$targetType instanceof ClassType) {
            throw new TypeError(
                "Appel '.' sur un '{$targetType}' : seul un objet supporte l'accès méthode",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }

        $virtual = new MethodCallExpr(
            $expr->line(),
            $expr->column(),
            $expr->target,
            $expr->method,
            $expr->args,
        );
        return $this->checkMethodCall($virtual, $ctx);
    }

    private function checkNew(NewExpr $expr, Context $ctx): Type
    {
        $class = $this->globals->getClass($expr->className);
        if ($class === null) {
            throw new TypeError(
                "Classe '{$expr->className}' inconnue",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }

        if ($class->isInterface) {
    throw new TypeError(
        "Impossible d'instancier une interface '{$expr->className}'",
        $this->file,
        $expr->line(),
        $expr->column(),
    );
}

if (in_array('abstract', $class->modifiers, true)) {
    throw new TypeError(
        "Impossible d'instancier la classe abstraite '{$expr->className}'",
        $this->file,
        $expr->line(),
        $expr->column(),
    );
}

        // Vérifier le constructeur
        // Résolution du constructeur
            $ctorOverloads = $class->methods['__construct'] ?? [];

            if (empty($ctorOverloads)) {
                if (!empty($expr->args)) {
                    throw new TypeError(
                        "La classe '{$class->name}' n'a pas de constructeur, "
                        . "mais " . count($expr->args) . " argument(s) sont fournis",
                        $this->file,
                        $expr->line(),
                        $expr->column(),
                    );
                }
            } else {
                $argTypes = array_map(
                    fn($arg) => $this->checkExpr($arg, $ctx),
                    $expr->args,
                );

                $this->overloads->resolve(
                    $ctorOverloads,
                    $argTypes,
                    "{$class->name}::__construct",
                    $this->file,
                    $expr->line(),
                    $expr->column(),
                );
            }

            return new ClassType($class->name);
    }

    private function checkInstanceof(InstanceofExpr $expr, Context $ctx): Type
    {
        $this->checkExpr($expr->operand, $ctx);
        if (!$this->globals->classExists($expr->className)) {
            throw new TypeError(
                "Classe '{$expr->className}' inconnue (instanceof)",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
        return ScalarType::bool();
    }

    // =========================================================================
    //  Utilitaires
    // =========================================================================

    private function requireBool(Expr $expr, Context $ctx, string $context): void
    {
        $t = $this->checkExpr($expr, $ctx);
        if (!$t instanceof ScalarType || $t->name !== ScalarType::BOOL) {
            throw new TypeError(
                "La {$context} doit être de type 'bool', reçu '{$t}'",
                $this->file,
                $expr->line(),
                $expr->column(),
            );
        }
    }

    /**
     * Vérifie qu'un type est valide (classe existante, etc.).
     */
    private function validateType(Type $type, int $line, int $column): void
    {
        if ($type instanceof ClassType) {
            $name = $type->name;
            if (in_array($name, ['object', 'callable', 'iterable', 'self', 'parent'], true)) {
                return;
            }
            if (!$this->globals->classExists($name)) {
                throw new TypeError(
                    "Classe '{$name}' inconnue",
                    $this->file,
                    $line,
                    $column,
                );
            }
        } elseif ($type instanceof ArrayType) {
            $this->validateType($type->element, $line, $column);
        } elseif ($type instanceof NullableType) {
            $this->validateType($type->inner, $line, $column);
        } elseif ($type instanceof UnionType) {
            foreach ($type->members as $m) {
                $this->validateType($m, $line, $column);
            }
        }
    }

    /**
 * Applique des narrowings à un scope donné.
 *
 * @param array<string, string> $narrowings
 */
private function applyNarrowings(Scope $scope, array $narrowings): void
{
    foreach ($narrowings as $varName => $op) {
        $symbol = $scope->lookup($varName);
        if ($symbol === null) {
            continue;
        }

        $narrowedType = $this->computeNarrowedType($symbol->type, $op);
        if ($narrowedType === null) {
            continue;
        }

        $scope->redefine(new Symbol(
            $varName,
            $symbol->kind,
            $narrowedType,
            $symbol->line,
            $symbol->column,
        ));
    }
}

private function computeNarrowedType(Type $current, string $op): ?Type
{
    if ($op === 'remove-null') {
        if ($current instanceof NullableType) {
            return $current->inner;
        }
        if ($current instanceof UnionType) {
            $members = array_values(array_filter(
                $current->members,
                fn(Type $m) => !$m instanceof NullType,
            ));
            if (count($members) === 0) {
                return new NullType();
            }
            if (count($members) === 1) {
                return $members[0];
            }
            return new UnionType($members);
        }
        return null;
    }

    if ($op === 'null') {
        return new NullType();
    }

    if (str_starts_with($op, 'instanceof:')) {
        $className = substr($op, strlen('instanceof:'));
        return new ClassType($className);
    }

    return null;
}

  // checkIf
}