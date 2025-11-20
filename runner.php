<?php

// CONFIGURACIÓN
$max_minutes = 350; // Para que dure casi todo el run (~5h50m)
$interval_seconds = 120; // Intentar cada 2 minutos
$start_time = time();

echo "=== Runner iniciado ===\n";

// BUCLE PRINCIPAL
while (true) {

    $elapsed = (time() - $start_time) / 60;

    // Si pasa el tiempo límite, terminamos
    if ($elapsed >= $max_minutes) {
        echo "Tiempo máximo alcanzado. Terminando runner.\n";
        exit;
    }

    echo "\n=== Intento de creación ===\n";

    // Ejecutar la creación
    $cmd = "oci compute instance launch --from-json file://instance.json 2>&1";
    $output = shell_exec($cmd);

    echo $output . "\n";

    // Verificar si la salida incluye un OCID, lo que indica que sí se creó
    if (preg_match('/ocid1\.instance\..+?\"/', $output, $matches)) {
        echo "¡Instancia creada! OCID detectado: " . $matches[0] . "\n";
        exit;
    }

    echo "No se creó la instancia. Reintentando en {$interval_seconds} segundos...\n";
    sleep($interval_seconds);
}
