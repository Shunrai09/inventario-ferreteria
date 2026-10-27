<?php
// Genera el hash de una contraseña con el mismo método que usa el sistema (password_hash).
// Sirve para crear el primer usuario directamente en la base de datos.
// Uso: php herramientas/generar_hash.php "MiClave"

if($argc < 2){
    fwrite(STDERR, "Uso: php herramientas/generar_hash.php \"MiClave\"" . PHP_EOL);
    exit(1);
}

$password = $argv[1];

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Contraseña: " . $password . PHP_EOL;
echo "Hash: " . $hash . PHP_EOL;
echo "Verificación: " . (password_verify($password, $hash) ? "OK" : "FALLÓ") . PHP_EOL;
