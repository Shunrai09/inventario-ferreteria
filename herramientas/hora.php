<?php
// Muestra la fecha y hora que usa el sistema según la zona horaria de config/config.php.
// Uso: php herramientas/hora.php
require_once __DIR__ . "/../app/bootstrap.php";

echo date('Y-m-d H:i:s') . " (" . date_default_timezone_get() . ")" . PHP_EOL;
