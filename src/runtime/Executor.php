<?php

declare(strict_types=1);

namespace PPhp\Runtime;

/**
 * Exécute du code PHP généré via un sous-process `php`,
 * en lui passant le code sur stdin.
 *
 * Transmet aussi les arguments CLI et réécrit les erreurs
 * runtime en utilisant la source map.
 */
final class Executor
{
    /**
     * @param string   $sourceFile Le fichier .pphp d'origine
     * @param string[] $args       Arguments à transmettre au script
     */
    public function __construct(
        private readonly string $sourceFile,
        private readonly array $args = [],
    ) {}

    public function run(string $phpCode, SourceMap $map): int
    {
        $phpBinary = PHP_BINARY;

        // On prépare $argv tel qu'il apparaîtra côté script exécuté.
        // $argv[0] = nom du script source, puis les args.
        $argv = array_merge([$this->sourceFile], $this->args);

        // On injecte un petit préambule pour positionner $argv et $argc,
        // puis on exécute le code utilisateur. On encapsule dans un script
        // temporaire côté process PHP, mais sans toucher au disque.
        //
        // Note : `php` en mode stdin ne définit pas $argv par défaut ;
        // on le lui fournit via un wrapper.
        $preamble = "<?php\n";
        $preamble .= '$argv = ' . var_export($argv, true) . ";\n";
        $preamble .= '$argc = ' . count($argv) . ";\n";

        // On retire une éventuelle balise ouvrante du code généré
        // pour l'insérer proprement après le préambule.
        $body = $phpCode;
        if (str_starts_with($body, '<?php')) {
            $body = substr($body, 5);
        }

        $finalCode = $preamble . $body;

        $descriptors = [
            0 => ['pipe', 'r'],   // stdin
            1 => ['pipe', 'w'],   // stdout
            2 => ['pipe', 'w'],   // stderr
        ];

        $process = proc_open(
            [$phpBinary, '-d', 'display_errors=stderr'],
            $descriptors,
            $pipes,
        );

        if (!is_resource($process)) {
            fwrite(STDERR, "pphp: impossible de lancer le sous-process PHP\n");
            return 1;
        }

        fwrite($pipes[0], $finalCode);
        fclose($pipes[0]);

        // Streaming stdout/stderr en direct
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdoutOpen = true;
        $stderrOpen = true;
        while ($stdoutOpen || $stderrOpen) {
            $read = [];
            if ($stdoutOpen) $read[] = $pipes[1];
            if ($stderrOpen) $read[] = $pipes[2];
            if (empty($read)) break;

            $write = null; $except = null;
            if (stream_select($read, $write, $except, 0, 200000) === false) {
                break;
            }

            foreach ($read as $stream) {
                $data = fread($stream, 8192);
                if ($data === '' || $data === false) {
                    if (feof($stream)) {
                        if ($stream === $pipes[1]) { fclose($pipes[1]); $stdoutOpen = false; }
                        if ($stream === $pipes[2]) { fclose($pipes[2]); $stderrOpen = false; }
                    }
                    continue;
                }
                if ($stream === $pipes[1]) {
                    fwrite(STDOUT, $data);
                } else {
                    // Réécriture naïve des erreurs via la source map.
                    fwrite(STDERR, $this->rewriteStderr($data, $map));
                }
            }
        }

        return proc_close($process);
    }

    /**
     * Réécrit les messages d'erreur PHP pour pointer vers le .pphp source.
     * Version minimale : on cherche les motifs "on line N" et on remplace
     * N par la ligne source correspondante.
     */
    private function rewriteStderr(string $data, SourceMap $map): string
    {
        return preg_replace_callback(
            '/on line (\d+)/',
            function (array $m) use ($map): string {
                $gen = (int) $m[1];
                $src = $map->sourceLine($gen);
                if ($src === null) {
                    return $m[0];
                }
                return 'on line ' . $src . ' of ' . $map->sourceFile();
            },
            $data,
        ) ?? $data;
    }
}