<?php

$file = 'tests/Semantic/SubtypeTest.php';
$s = file_get_contents($file);
$pos = strpos($s, '<?pphp');

if ($pos === false) {
    echo "Balise non trouvée\n";
    exit;
}

echo "Position: $pos\n";
echo "Contenu autour (hex):\n";

for ($i = $pos; $i < $pos + 20; $i++) {
    $char = $s[$i] ?? '';
    $hex = dechex(ord($char));
    $visible = match ($char) {
        "\n" => '\n',
        "\r" => '\r',
        "\t" => '\t',
        ' '  => '␣',
        default => $char,
    };
    echo "  offset +" . ($i - $pos) . " : 0x$hex  '$visible'\n";
}