<?php

$testsDir = __DIR__ . '/tests';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $content = file_get_contents($file->getPathname());

    // Chercher <?pphp suivi d'un caractère qui n'est ni espace, ni tab, ni newline, ni \r
    if (preg_match_all('/<\?pphp([^ \t\n\r])/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        echo $file->getPathname() . " : " . count($matches[0]) . " occurrence(s)\n";
        foreach ($matches[0] as $match) {
            $offset = $match[1];
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $char = $match[0][6] ?? '?';
            $hex = dechex(ord($char));
            echo "  ligne $line : caractère suivant = '$char' (0x$hex)\n";
        }
    }
}