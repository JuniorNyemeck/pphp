<?php

$file = 'tests/lexer/LexerTest.php';
$content = file_get_contents($file);

preg_match_all('/<\?pphp[^ \t\n\r]/', $content, $matches, PREG_OFFSET_CAPTURE);

foreach ($matches[0] as $match) {
    $offset = $match[1];
    $line = substr_count(substr($content, 0, $offset), "\n") + 1;
    $char = $match[0][6] ?? '?';
    $hex = dechex(ord($char));
    echo "Ligne $line : caractère = '$char' (0x$hex)\n";
    echo "Contexte : " . substr($content, $offset - 20, 50) . "\n\n";
}