<?php
// Hace un respaldo de la base de datos en la carpeta respaldos/ (y en la copia, si está configurada).
// Uso: php herramientas/respaldar.php
// Sirve para programarlo en el Programador de tareas de Windows además de los respaldos automáticos.
require_once __DIR__ . "/../app/bootstrap.php";

try {
    $respaldo = crearRespaldo('manual');
} catch(Throwable $e) {
    guardarErrorRespaldo('No se pudo hacer el respaldo: ' . $e->getMessage());
    fwrite(STDERR, 'No se pudo hacer el respaldo: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
echo 'Respaldo creado: ' . carpetaRespaldos() . '/' . $respaldo['archivo'] . PHP_EOL;
if($respaldo['aviso']){
    fwrite(STDERR, $respaldo['aviso'] . PHP_EOL);
    exit(1);
}
