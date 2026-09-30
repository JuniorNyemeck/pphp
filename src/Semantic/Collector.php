<?php

declare(strict_types=1);

namespace PPhp\Semantic;

use PPhp\Error\TypeError;
use PPhp\Parser\Node\Stmt\ClassDeclStmt;
use PPhp\Parser\Node\Stmt\FunctionDeclStmt;
use PPhp\Parser\Node\Stmt\MethodDeclStmt;
use PPhp\Parser\Node\Stmt\ProgramNode;
use PPhp\Parser\Node\Stmt\PropertyDeclStmt;
use PPhp\Semantic\Type\ClassType;
use PPhp\Semantic\Type\TypeFactory;

/**
 * Passe 1 : collecte des déclarations top-level et des membres de classes.
 * Ne vérifie pas les corps.
 */
final class Collector
{
    private GlobalScope $globals;

    private ?SignatureChecker $signatureChecker = null;

    public function __construct()
    {
        $this->globals = new GlobalScope();
    }

    public function collect(ProgramNode $program, string $file): GlobalScope
{
    // Première boucle : collecter les noms (pour permettre les références croisées)
    foreach ($program->statements as $stmt) {
        if ($stmt instanceof ClassDeclStmt) {
            $this->collectClassHeader($stmt, $file);
        } elseif ($stmt instanceof FunctionDeclStmt) {
            $this->collectFunctionHeader($stmt, $file);
        }
    }

    // Deuxième boucle : collecter les membres de classes
    foreach ($program->statements as $stmt) {
        if ($stmt instanceof ClassDeclStmt) {
            $this->collectClassMembers($stmt, $file);
        }
    }

    // Valider les surcharges (unicité + disjonction)
    $hierarchy = new ClassHierarchy($this->globals);
    $subtypes = new SubtypeChecker($this->globals, $hierarchy);
    $this->signatureChecker = new SignatureChecker($this->globals, $hierarchy, $subtypes);

    // Fonctions top-level
    foreach ($this->globals->functions as $name => $overloads) {
        if (count($overloads) > 1) {
            $this->signatureChecker->checkOverloads($overloads, $name, $file);
        }
    }

    // Méthodes de classe
    foreach ($this->globals->classes as $class) {
        foreach ($class->methods as $name => $overloads) {
            if (count($overloads) > 1) {
                $this->signatureChecker->checkOverloads(
                    $overloads,
                    "{$class->name}::{$name}",
                    $file,
                );
            }
        }
    }

    return $this->globals;
}
    private function collectClassHeader(ClassDeclStmt $stmt, string $file): void
    {
        // Vérifier que le parent existe (s'il est déclaré)
        if ($stmt->extends !== null && !$this->globals->classExists($stmt->extends)) {
            // On tolère pour l'instant : la classe parente peut être déclarée plus loin.
            // On fera la vérification à la fin de la collecte.
        }

        $info = new ClassInfo(
            name: $stmt->name,
            parent: $stmt->extends,
            implements: $stmt->implements,
            modifiers: $stmt->modifiers,
            isInterface: $stmt->isInterface,
            line: $stmt->line(),
            column: $stmt->column(),
        );

        $this->globals->defineClass($info);
    }

    private function collectClassMembers(ClassDeclStmt $stmt, string $file): void
    {
        $info = $this->globals->getClass($stmt->name);
        if ($info === null) {
            return; // ne devrait pas arriver
        }

        foreach ($stmt->members as $member) {
            if ($member instanceof PropertyDeclStmt) {
                $this->collectProperty($info, $member, $file);
            } elseif ($member instanceof MethodDeclStmt) {
                $this->collectMethod($info, $member, $file);
            }
        }
    }

    private function collectProperty(ClassInfo $class, PropertyDeclStmt $stmt, string $file): void
    {
        $type = TypeFactory::fromNode($stmt->type, $class->name, $class->parent);

        foreach ($stmt->declarators as $d) {
            $name = $d['name'];
            if (isset($class->properties[$name])) {
                $existing = $class->properties[$name];
                throw new TypeError(
                    "Redéclaration de la propriété '{$name}' dans la classe '{$class->name}' "
                    . "(déjà déclarée à la ligne {$existing->line})",
                    $file,
                    $d['line'],
                    $d['column'],
                    
                );
            }
            $class->properties[$name] = new PropertyInfo(
                name: $name,
                type: $type,
                modifiers: $stmt->modifiers,
                line: $d['line'],
                column: $d['column'],
                declaringClass: $class->name, 
            );
        }
    }

        private function collectMethod(ClassInfo $class, MethodDeclStmt $stmt, string $file): void
    {
        $returnType = $stmt->returnType !== null
    ? TypeFactory::fromNode($stmt->returnType, $class->name, $class->parent)
    : null;

if ($returnType === null
    && !in_array($stmt->name, ['__construct', '__destruct'], true)
) {
    throw new TypeError(
        "Type de retour obligatoire pour la méthode '{$stmt->name}'",
        $file,
        $stmt->line(),
        $stmt->column(),
    );
}

        $info = new MethodInfo(
            name: $stmt->name,
            modifiers: $stmt->modifiers,
            params: $stmt->params,
            returnType: $returnType,
            ast: $stmt,
            line: $stmt->line(),
            column: $stmt->column(),
            declaringClass: $class->name,
        );

        // Ajouter aux surcharges
        $class->methods[$stmt->name][] = $info;
    }

       private function collectFunctionHeader(FunctionDeclStmt $stmt, string $file): void
{
    $returnType = $stmt->returnType !== null
        ? TypeFactory::fromNode($stmt->returnType)
        : null;

    if ($returnType === null) {
        throw new TypeError(
            "Type de retour obligatoire pour la fonction '{$stmt->name}'",
            $file,
            $stmt->line(),
            $stmt->column(),
        );
    }

    $info = new FunctionInfo(
        name: $stmt->name,
        params: $stmt->params,
        returnType: $returnType,
        line: $stmt->line(),
        column: $stmt->column(),
        ast: $stmt,
    );

    $this->globals->defineFunction($info);
}
}