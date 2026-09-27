#!/usr/bin/env php
<?php

declare(strict_types=1);

use PPhp\Compiler;
use PPhp\Error\PPhpError;
use PPhp\Runtime\Executor;

// Autoload : composer s'il existe, sinon fallback maison
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'PPhp\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

function usage(): void
{
    $bin = basename($_SERVER['argv'][0] ?? 'pphp');
    fwrite(STDERR, <<<TXT
    Usage:
      {$bin} <fichier.pphp> [args...]        Compile et exécute
      {$bin} --emit <fichier.pphp>           Affiche le PHP généré (sans exécuter)
      {$bin} --emit=<sortie.php> <fichier>   Écrit le PHP généré dans un fichier
      {$bin} --help                          Affiche cette aide

    TXT);
}

$argvFull = $_SERVER['argv'] ?? [];
$script = array_shift($argvFull);

if (empty($argvFull)) {
    usage();
    exit(1);
}

$emitMode = false;       // false | 'stdout' | 'file'
$emitTarget = null;
$sourceFile = null;
$scriptArgs = [];

foreach ($argvFull as $i => $arg) {
    if ($arg === '--help' || $arg === '-h') {
        usage();
        exit(0);
    } elseif ($arg === '--emit') {
        $emitMode = 'stdout';
        continue;
    } elseif (str_starts_with($arg, '--emit=')) {
        $emitMode = 'file';
        $emitTarget = substr($arg, strlen('--emit='));
        continue;
    } elseif ($sourceFile === null) {
        $sourceFile = $arg;
        continue;
    } else {
        $scriptArgs = array_slice($argvFull, $i);
        break;
    }
}

if ($sourceFile === null) {
    usage();
    exit(1);
}

if (!is_file($sourceFile)) {
    fwrite(STDERR, "pphp: fichier introuvable : {$sourceFile}\n");
    exit(1);
}

try {
    $source = file_get_contents($sourceFile);
    if ($source === false) {
        throw new RuntimeException("Impossible de lire {$sourceFile}");
    }

    $compiler = new Compiler();
    $result = $compiler->compile($source, $sourceFile);
    // $result : ['code' => string, 'sourceMap' => SourceMap]

    if ($emitMode === 'stdout') {
        fwrite(STDOUT, $result['code']);
        exit(0);
    }

    if ($emitMode === 'file') {
        file_put_contents($emitTarget, $result['code']);
        exit(0);
    }

    // Mode normal : exécution
    $executor = new Executor($sourceFile, $scriptArgs);
    $exitCode = $executor->run($result['code'], $result['sourceMap']);
    exit($exitCode);

} catch (PPhpError $e) {
    fwrite(STDERR, $e->format() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "pphp: erreur interne : " . $e->getMessage() . "\n");
    exit(1);
}