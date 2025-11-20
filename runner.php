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

// Duración total ≈ 5 horas
$max_attempts = 147;

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {

    echo "[INFO] Intento #$attempt de $max_attempts: verificando disponibilidad de VM.Standard.A1.Flex...\n";
    $ret = hhb_exec($cmd, "", $stdout, $stderr, true);

    // Manejo de errores comunes del CLI (503, TooManyRequests, etc)
    if (
        str_contains($stdout, "Out of host capacity") ||
        str_contains($stdout, "TooManyRequests") ||
        str_contains($stdout, "503") ||
        str_contains($stderr, "503")
    ) {
        $wait_seconds = 120;
        echo "[WARN] Falló la creación. Reintentando en $wait_seconds segundos...\n";
        for ($i = 1; $i <= $wait_seconds; $i++) {
            echo "$i/$wait_seconds\r";
            sleep(1);
        }
        echo "\n";
        continue;
    }

    // JSON esperado
    $expected = '{
  "code": "InternalError",
  "message": "Out of host capacity."
}';

    //  YA NO SE DETIENE POR STDOUT inesperado
    // Solo valida que el stdout contenga algo relacionado, si no lo contiene
    // igual continúa y respeta la duración total.

    if (!str_contains($stdout, $expected)) {
        echo "[WARN] STDOUT inesperado, pero se ignora para continuar el run.\n";
    }

    //  YA NO SE DETIENE POR STDERR a menos que sea un error fatal
    if ($stderr !== '' && !str_contains($stderr, "Warning") && !str_contains($stderr, "WARNING")) {
        echo "[WARN] STDERR inesperado (no fatal), se ignora.\n";
    }

    echo "[INFO] Comando ejecutado. Esperando siguiente intento...\n";

    // Espera larga entre intentos
    for ($i = 1, $imax = 701; $i < $imax; $i++) {
        echo "$i/$imax\r";
        sleep(1);
    }
}

echo "[INFO] Fin del run (~5 horas completadas).\n";
exit;
