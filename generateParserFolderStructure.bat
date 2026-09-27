@echo off
setlocal

REM === Racine ===
set ROOT=src\Parser

REM === Création des dossiers ===
mkdir "%ROOT%" 2>nul
mkdir "%ROOT%\Node" 2>nul
mkdir "%ROOT%\Node\Stmt" 2>nul
mkdir "%ROOT%\Node\Expr" 2>nul

REM === Fichiers à la racine de src/Parser ===
type nul > "%ROOT%\TypeNode.php"
type nul > "%ROOT%\Parser.php"
type nul > "%ROOT%\AstPrinter.php"

REM === Fichiers dans src/Parser/Node ===
type nul > "%ROOT%\Node\Node.php"
type nul > "%ROOT%\Node\ProgramNode.php"

REM === Fichiers dans src/Parser/Node/Stmt ===
type nul > "%ROOT%\Node\Stmt\EchoStmt.php"
type nul > "%ROOT%\Node\Stmt\ReturnStmt.php"
type nul > "%ROOT%\Node\Stmt\IfStmt.php"
type nul > "%ROOT%\Node\Stmt\WhileStmt.php"
type nul > "%ROOT%\Node\Stmt\ForStmt.php"
type nul > "%ROOT%\Node\Stmt\ForeachStmt.php"
type nul > "%ROOT%\Node\Stmt\BlockStmt.php"
type nul > "%ROOT%\Node\Stmt\ExprStmt.php"
type nul > "%ROOT%\Node\Stmt\VarDeclStmt.php"
type nul > "%ROOT%\Node\Stmt\FunctionDeclStmt.php"
type nul > "%ROOT%\Node\Stmt\ClassDeclStmt.php"

REM === Fichiers dans src/Parser/Node/Expr ===
type nul > "%ROOT%\Node\Expr\BinaryExpr.php"
type nul > "%ROOT%\Node\Expr\UnaryExpr.php"
type nul > "%ROOT%\Node\Expr\AssignExpr.php"
type nul > "%ROOT%\Node\Expr\CallExpr.php"
type nul > "%ROOT%\Node\Expr\IndexExpr.php"
type nul > "%ROOT%\Node\Expr\PropertyAccessExpr.php"
type nul > "%ROOT%\Node\Expr\MethodCallExpr.php"
type nul > "%ROOT%\Node\Expr\NewExpr.php"
type nul > "%ROOT%\Node\Expr\VariableExpr.php"
type nul > "%ROOT%\Node\Expr\LiteralExpr.php"
type nul > "%ROOT%\Node\Expr\TernaryExpr.php"
type nul > "%ROOT%\Node\Expr\CoalesceExpr.php"

echo.
echo Structure creee avec succes dans %ROOT%\
echo.
endlocal
pause