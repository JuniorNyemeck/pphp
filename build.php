<?php

declare(strict_types=1);

/**
 * Construit le PHAR de PPHP.
 * Usage : php -d phar.readonly=0 build.php
 * Résultat : build/pphp.phar
 */

$root = __DIR__;
$outputDir = $root . '/build';
$pharFile = $outputDir . '/pphp.phar';

// Nettoyage
if (file_exists($pharFile)) {
    unlink($pharFile);
}
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Création du PHAR
$phar = new Phar($pharFile, 0, 'pphp.phar');
$phar->startBuffering();

// Ajouter tous les fichiers .php (src/, bin/pphp.php)
$phar->buildFromDirectory($root, '/^.*\.php$/');

// Le stub pointe vers bin/pphp.php
$phar->setStub(<<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('pphp.phar');
require 'phar://pphp.phar/bin/pphp.php';
__HALT_COMPILER();
STUB
);

$phar->stopBuffering();

// Rendre exécutable
chmod($pharFile, 0755);

echo "PHAR created: {$pharFile}\n";
echo "Size: " . number_format(filesize($pharFile) / 1024, 2) . " KB\n";