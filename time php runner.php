<?php
declare(strict_types=1);

// Protección contra doble ejecución
$lock = fopen(__FILE__, 'rb');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    die("Already running.\n");
}

/**
 * Función robusta para ejecutar comandos shell con stdin, stdout, stderr y return code
 */
function hhb_exec(string $cmd, string $stdin = "", string &$stdout = null, string &$stderr = null, bool $print_std = false): int
{
    $stdouth = tmpfile();
    $stderrh = tmpfile();
    $descriptorspec = [
        0 => ["pipe", "rb"],  // stdin
        1 => ["file", stream_get_meta_data($stdouth)['uri'], 'ab'],
        2 => ["file", stream_get_meta_data($stderrh)['uri'], 'ab'],
    ];

    $pipes = [];
    $proc = proc_open($cmd, $descriptorspec, $pipes);

    // Escribir stdin
    while (strlen($stdin) > 0) {
        $written_now = fwrite($pipes[0], $stdin);
        if ($written_now < 1 || $written_now === strlen($stdin)) break;
        $stdin = substr($stdin, $written_now);
    }
    fclose($pipes[0]);
    unset($stdin, $pipes[0]);

    if (!$print_std) {
        $proc_ret = proc_close($proc);
        $stdout = stream_get_contents($stdouth);
        $stderr = stream_get_contents($stderrh);
    } else {
        $stdout = "";
        $stderr = "";
        stream_set_blocking($stdouth, false);
        stream_set_blocking($stderrh, false);
        $fetchstd = function () use (&$stdout, &$stderr, &$stdouth, &$stderrh): bool {
            $ret = false;
            $tmp = stream_get_contents($stdouth);
            if (is_string($tmp) && strlen($tmp) > 0) {
                $ret = true;
                $stdout .= $tmp;
                fwrite(STDOUT, $tmp);
            }
            $tmp = stream_get_contents($stderrh);
            if (is_string($tmp) && strlen($tmp) > 0) {
                $ret = true;
                $stderr .= $tmp;
                fwrite(STDERR, $tmp);
            }
            return $ret;
        };

        while (($status = proc_get_status($proc))["running"]) {
            if (!$fetchstd()) usleep(100 * 1000); // 100 ms
        }
        $proc_ret = $status["exitcode"];
        proc_close($proc);
        $fetchstd();
    }

    fclose($stdouth);
    fclose($stderrh);
    return $proc_ret;
}

// Script principal de reintento
$cmd = "php index.php 2>&1";

for ($attempt = 1;; $attempt++) {
    echo "[INFO] Intento #$attempt: verificando disponibilidad de VM.Standard.A1.Flex...\n";
    $ret = hhb_exec($cmd, "", $stdout, $stderr, true);

    // Detectar errores de capacidad o "TooManyRequests"
    if (str_contains($stdout, "Out of host capacity") || str_contains($stdout, "TooManyRequests")) {
        $wait_seconds = 120; // espera 2 minutos, puedes cambiar a 30
        echo "[WARN] Falló la creación. Reintentando en $wait_seconds segundos...\n";
        for ($i = 1; $i <= $wait_seconds; $i++) {
            echo "$i/$wait_seconds\r";
            sleep(1);
        }
        echo "\n";
        continue;
    }

    // Validación estricta de salida
    $expected = '{
  "code": "InternalError",
  "message": "Out of host capacity."
}';

    if (!str_contains($stdout, $expected)) {
        echo "Unexpected STDOUT output, dumping...\n";
        file_put_contents(__FILE__ . ".actual", var_export($stdout, true));
        file_put_contents(__FILE__ . ".expected", var_export($expected, true));
        die("Terminado por salida inesperada.\n");
    }

    if ($stderr !== '') {
        echo "Unexpected STDERR output, dumping...\n";
        file_put_contents(__FILE__ . ".actual_stderr", var_export($stderr, true));
        die("Terminado por error inesperado.\n");
    }

    echo "[INFO] Comando ejecutado correctamente.\n";

    // Espera entre ejecuciones normales
    for ($i = 1, $imax = 701; $i < $imax; $i++) {
        echo "$i/$imax\r";
        sleep(1);
    }
}
