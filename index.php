<?php
declare(strict_types=1);

/**
 * index.php
 * Script para crear una instancia flexible en OCI con Oracle Linux 9
 * Listo para ser llamado desde retry.php
 */

// ---------- CONFIGURACIÓN ----------

$compartment_id = "ocid1.tenancy.oc1..aaaaaaaanpdgknsao7q7fkhgm7rhhta6oo2qvxrmd6b7v5dvigljdsooztgq";
$availability_domain = "ddme:MX-QUERETARO-1-AD-1";
$shape = "VM.Standard.A1.Flex";
$image_id = "ocid1.image.oc1.mx-queretaro-1.aaaaaaaaghkhc3e3blo7ocpqfi7g35bhq4l6gqotwimwnvqjthfbkulwnnca";
$instance_name = "Minecraft" . time();

// Recursos flexibles
$shape_config = json_encode([
    "ocpus" => 4,
    "memoryInGBs" => 24
]);

// Disco de arranque en GB
$boot_volume_size_in_gbs = 200;

// VCN y Subnet
$vcn_id = "ocid1.vcn.oc1.mx-queretaro-1.amaaaaaarza6fxqae6ps4wknuh4ljt7iqatcpcejrsf4cfpjmov3vcfzpwma";
$subnet_id = "ocid1.subnet.oc1.mx-queretaro-1.aaaaaaaazmk7uzhrr4y5za3qirdcf6hacmuortkmekeo7uipgwrc4lzhw7qq";

// Asignar IP pública
$assign_public_ip = true;

// ---------- FUNCIONES ----------

/**
 * Ejecuta un comando y devuelve stdout y stderr
 */
function run_cmd(string $cmd, ?string &$output = null, ?string &$error = null): int {
    $descriptorspec = [
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"], // stderr
    ];
    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (!is_resource($process)) return 1;

    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return proc_close($process);
}

// ---------- CREAR INSTANCIA ----------

$cmd = sprintf(
    'oci compute instance launch --compartment-id %s --availability-domain %s --shape %s --shape-config \'%s\' --image-id %s --subnet-id %s --display-name %s --boot-volume-size-in-gbs %d %s',
    escapeshellarg($compartment_id),
    escapeshellarg($availability_domain),
    escapeshellarg($shape),
    $shape_config,
    escapeshellarg($image_id),
    escapeshellarg($subnet_id),
    escapeshellarg($instance_name),
    $boot_volume_size_in_gbs,
    $assign_public_ip ? '--assign-public-ip true' : ''
);

echo "[INFO] Intentando crear instancia...\n";

$ret = run_cmd($cmd, $stdout, $stderr);

if ($ret === 0) {
    echo "[INFO] Instancia creada:\n$stdout\n";
} else {
    echo "[ERROR] Falló la creación:\nSTDOUT:\n$stdout\nSTDERR:\n$stderr\n";
    exit(1);
}
