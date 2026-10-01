<?php

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/tests'));
$found = false;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    if (preg_match_all('/<\?pphp[^ \t\n\r]/', $content, $matches)) {
        echo $file->getPathname() . " : " . count($matches[0]) . " occurrence(s)\n";
        $found = true;
    }
}

if (!$found) {
    echo "Aucune occurrence problématique trouvée.\n";
}