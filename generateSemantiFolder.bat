@echo off
setlocal

REM === Racine ===
set ROOT=src\Semantic

REM === Création des dossiers ===
mkdir "%ROOT%" 2>nul
mkdir "%ROOT%\Type" 2>nul

REM === Fichiers à la racine de src/Semantic ===
type nul > "%ROOT%\Symbol.php"
type nul > "%ROOT%\SymbolKind.php"
type nul > "%ROOT%\Scope.php"
type nul > "%ROOT%\GlobalScope.php"
type nul > "%ROOT%\ClassInfo.php"
type nul > "%ROOT%\FunctionInfo.php"
type nul > "%ROOT%\SubtypeChecker.php"
type nul > "%ROOT%\OperatorTypeTable.php"
type nul > "%ROOT%\TypeChecker.php"
type nul > "%ROOT%\ErrorFormatter.php"

REM === Fichiers dans src/Semantic/Type ===
type nul > "%ROOT%\Type\Type.php"
type nul > "%ROOT%\Type\ScalarType.php"
type nul > "%ROOT%\Type\ArrayType.php"
type nul > "%ROOT%\Type\NullableType.php"
type nul > "%ROOT%\Type\UnionType.php"
type nul > "%ROOT%\Type\ClassType.php"
type nul > "%ROOT%\Type\MixedType.php"
type nul > "%ROOT%\Type\VoidType.php"
type nul > "%ROOT%\Type\NeverType.php"
type nul > "%ROOT%\Type\NullType.php"
type nul > "%ROOT%\Type\TypeFactory.php"

echo.
echo Structure creee avec succes dans %ROOT%\
echo.
endlocal
pause