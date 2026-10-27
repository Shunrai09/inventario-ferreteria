<?php
// Descarga un respaldo de la carpeta respaldos/ (solo administradores). ?archivo=nombre del respaldo.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Solo nombres de respaldo válidos: no se puede pedir otro archivo del servidor
$archivo = basename($_GET['archivo'] ?? '');
$ruta = carpetaRespaldos() . '/' . $archivo;
if(!leerNombreRespaldo($archivo) || !is_file($ruta)){
    guardarMensaje('danger', 'El respaldo elegido no existe.');
    redirigir('respaldos/index.php');
}

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: no-store');
readfile($ruta);
