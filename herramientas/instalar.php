<?php
// Instala el sistema en una PC nueva: crea la base de datos y sus tablas, un usuario de MySQL con permisos
// solo sobre esa base y el primer administrador del sistema, y guarda la conexión en config/config.php
// (si no existe, lo crea a partir de config/config.example.php y conserva todo lo demás).
//
// Normalmente se ejecuta con instalar.bat, que pide las contraseñas sin mostrarlas y las pasa en:
//   INSTALAR_CLAVE_ROOT   contraseña de root de MySQL (en XAMPP está vacía)
//   INSTALAR_USUARIO      usuario del administrador del sistema (si falta: admin)
//   INSTALAR_CLAVE_ADMIN  su contraseña (si falta se genera una)
//
// Se puede repetir sin riesgo: no borra tablas ni datos y, si ya hay un administrador, no crea otro.
// También sirve para actualizar: con una versión nueva del sistema aplica los cambios de la base que falten
// (database/migraciones/), después de respaldarla.
// Termina con 0 si todo salió bien, 2 si MySQL rechazó la contraseña de root, 3 si MySQL no está
// encendido y 1 ante cualquier otro problema.

$raiz = dirname(__DIR__);
$rutaConfig = $raiz . '/config/config.php';
$rutaEjemplo = $raiz . '/config/config.example.php';

function linea($texto = '') {
    echo $texto . PHP_EOL;
}

function salir($codigo, $mensaje) {
    fwrite(STDERR, PHP_EOL . $mensaje . PHP_EOL);
    exit($codigo);
}

// Ejecuta un archivo SQL sentencia por sentencia (cada una termina en ; al final de la línea; sin comentarios --).
// Con $sinDatos se saltan los INSERT (las categorías iniciales no se vuelven a crear en una base que ya existía).
function ejecutarSql($pdo, $sql, $sinDatos = false) {
    foreach (preg_split('/;\s*$/m', preg_replace('/^\s*--.*$/m', '', $sql)) as $sentencia) {
        if (trim($sentencia) !== '' && !($sinDatos && preg_match('/^\s*INSERT\b/i', $sentencia))) {
            $pdo->exec($sentencia);
        }
    }
}

// Contraseña legible: sin letras ni números que se confundan (l, I, O, 0, 1)
function generarClave($largo = 12) {
    $caracteres = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $clave = '';
    for ($i = 0; $i < $largo; $i++) {
        $clave .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $clave;
}

// Cambia solo el usuario y la contraseña de la sección base_datos de config/config.php.
// Si el resultado no se lee bien, deja el archivo como estaba y devuelve false.
function guardarConexion($rutaConfig, $rutaEjemplo, $usuario, $clave) {
    $original = is_file($rutaConfig) ? file_get_contents($rutaConfig) : null;
    $texto = preg_replace_callback("/'base_datos'\s*=>\s*\[.*?\n\s*\]/s", function ($bloque) use ($usuario, $clave) {
        $nuevo = preg_replace_callback("/('usuario'\s*=>\s*)'[^']*'/", fn($m) => $m[1] . "'$usuario'", $bloque[0], 1);
        return preg_replace_callback("/('password'\s*=>\s*)'[^']*'/", fn($m) => $m[1] . "'$clave'", $nuevo, 1);
    }, $original ?? file_get_contents($rutaEjemplo), 1);

    if ($texto !== null && file_put_contents($rutaConfig, $texto) !== false) {
        $config = require $rutaConfig;
        if (($config['base_datos']['usuario'] ?? null) === $usuario && ($config['base_datos']['password'] ?? null) === $clave) {
            return true;
        }
    }
    if ($original === null) {
        @unlink($rutaConfig);
    } else {
        file_put_contents($rutaConfig, $original);
    }
    return false;
}

// 1. Requisitos de PHP
if (PHP_VERSION_ID < 80000) {
    salir(1, 'Se necesita PHP 8.0 o superior y este es ' . PHP_VERSION . '.');
}
$faltan = array_values(array_filter(['pdo_mysql', 'mbstring'], fn($extension) => !extension_loaded($extension)));
if ($faltan) {
    salir(1, 'A PHP le faltan las extensiones ' . implode(' y ', $faltan) . '.' . PHP_EOL
        . 'Abre ' . (php_ini_loaded_file() ?: 'el php.ini (si no existe, copia php.ini-development como php.ini en la carpeta de PHP)')
        . PHP_EOL . 'y quita el ; del inicio de estas líneas: extension_dir = "ext", extension=' . implode(', extension=', $faltan));
}

// 2. Datos de conexión: los de config/config.php si ya existe (conserva empresa, tiendas, etc.) o los de la plantilla
$config = require (is_file($rutaConfig) ? $rutaConfig : $rutaEjemplo);
$host = $config['base_datos']['host'];
$nombreBd = $config['base_datos']['nombre'];
$usuarioBd = $config['base_datos']['usuario'] === 'root' ? 'inventario_app' : $config['base_datos']['usuario'];
foreach ([$nombreBd, $usuarioBd] as $nombre) {
    if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $nombre)) {
        salir(1, "Nombre no válido en config/config.php (sección base_datos): '$nombre'. Usa solo letras, números y _.");
    }
}

linea('Instalando el sistema en ' . $raiz);
linea('PHP ' . PHP_VERSION);

// 3. Conexión como root
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", 'root', (string) getenv('INSTALAR_CLAVE_ROOT'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    preg_match('/\[(\d{4})\]/', $e->getMessage(), $codigo);
    switch ($codigo[1] ?? '') {
        case '1045':
        case '1698':
            salir(2, 'MySQL rechazó la contraseña de root.');
        case '2002':
        case '2003':
        case '2006':
            salir(3, "No se pudo conectar con MySQL en $host. Revisa que esté encendido (en XAMPP: botón Start de MySQL).");
        default:
            salir(1, 'No se pudo conectar con MySQL: ' . $e->getMessage());
    }
}
linea('MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn());

try {
    // 4. Base de datos y tablas (esquema.sql solo crea lo que falta)
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$nombreBd` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$nombreBd`");
    $existia = (bool) $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
    ejecutarSql($pdo, file_get_contents($raiz . '/database/esquema.sql'), $existia);

    // 5. Usuario de MySQL del sistema, con una contraseña nueva que solo queda en config/config.php.
    //    Se crea para las tres direcciones con las que PHP puede llegar a MySQL dentro de la misma PC.
    //    La clave mezcla mayúsculas, minúsculas, números y un guion por si MySQL exige claves fuertes (validate_password).
    do {
        $claveBd = generarClave(12) . '-' . generarClave(12);
    } while (!preg_match('/[a-z]/', $claveBd) || !preg_match('/[A-Z]/', $claveBd) || !preg_match('/\d/', $claveBd));
    foreach (['localhost', '127.0.0.1', '::1'] as $origen) {
        $cuenta = "'$usuarioBd'@'$origen'";
        $pdo->exec("CREATE USER IF NOT EXISTS $cuenta IDENTIFIED BY " . $pdo->quote($claveBd));
        $pdo->exec("ALTER USER $cuenta IDENTIFIED BY " . $pdo->quote($claveBd));
        $pdo->exec("GRANT ALL PRIVILEGES ON `$nombreBd`.* TO $cuenta");
    }
    if (!guardarConexion($rutaConfig, $rutaEjemplo, $usuarioBd, $claveBd)) {
        salir(1, "No se pudo actualizar config/config.php. Escribe a mano en la sección base_datos:" . PHP_EOL
            . "    'usuario'  => '$usuarioBd'," . PHP_EOL . "    'password' => '$claveBd',");
    }
    new PDO("mysql:host=$host;dbname=$nombreBd;charset=utf8mb4", $usuarioBd, $claveBd);
    linea("Usuario de MySQL $usuarioBd listo y guardado en config/config.php.");

    // 6. Bases de una versión anterior del sistema: se aplican los cambios de database/migraciones/ que falten.
    //    La primera línea de cada archivo ("-- comprobar: tabla.columna") dice qué crea; si ya existe, se salta.
    //    Antes de cambiar nada se guarda un respaldo con las funciones del propio sistema.
    $existeColumna = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
    $pendientes = [];
    foreach (glob($raiz . '/database/migraciones/*.sql') as $archivo) {
        $sql = file_get_contents($archivo);
        if (preg_match('/^-- comprobar: (\w+)\.(\w+)/', $sql, $comprobar)) {
            $existeColumna->execute([$nombreBd, $comprobar[1], $comprobar[2]]);
            if (!$existeColumna->fetchColumn()) {
                $pendientes[basename($archivo)] = $sql;
            }
        }
    }
    if ($pendientes) {
        require_once $raiz . '/app/bootstrap.php';
        try {
            $respaldo = crearRespaldo('antes_de_actualizar');
        } catch (Throwable $e) {
            salir(1, 'No se pudo respaldar la base antes de actualizarla, así que no se cambió nada: ' . $e->getMessage());
        }
        linea('Respaldo antes de actualizar: respaldos/' . $respaldo['archivo']);
        foreach ($pendientes as $archivo => $sql) {
            ejecutarSql($pdo, $sql);
            linea("Actualización aplicada: $archivo");
        }
    }
    $tablas = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
    $tablas->execute([$nombreBd]);
    linea("Base de datos $nombreBd lista (" . $tablas->fetchColumn() . ' tablas).');

    // 7. Primer administrador del sistema
    $administradores = $pdo->query("SELECT usuario FROM usuarios WHERE rol = 'administrador'")->fetchAll(PDO::FETCH_COLUMN);
    if ($administradores) {
        linea('Ya hay administrador (' . implode(', ', $administradores) . '), no se creó otro.');
        $usuarioAdmin = null;
    } else {
        $usuarioAdmin = trim((string) getenv('INSTALAR_USUARIO')) ?: 'admin';
        // Misma regla que el formulario de usuarios
        if (!preg_match('/^[a-zA-Z\s]{1,50}$/', $usuarioAdmin)) {
            salir(1, "El usuario '$usuarioAdmin' no es válido: solo letras sin tildes y espacios. Vuelve a ejecutar la instalación.");
        }
        $existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE usuario = ?');
        $existe->execute([$usuarioAdmin]);
        if ($existe->fetchColumn() > 0) {
            salir(1, "Ya existe un usuario '$usuarioAdmin'. Vuelve a ejecutar la instalación con otro nombre.");
        }
        $claveAdmin = (string) getenv('INSTALAR_CLAVE_ADMIN');
        $claveGenerada = $claveAdmin === '';
        if ($claveGenerada) {
            $claveAdmin = generarClave();
        }
        $pdo->prepare("INSERT INTO usuarios (usuario, nombre, telefono, direccion, password, rol) VALUES (?, 'Administrador', '', '', ?, 'administrador')")
            ->execute([$usuarioAdmin, password_hash($claveAdmin, PASSWORD_DEFAULT)]);
    }
} catch (PDOException $e) {
    salir(1, 'MySQL devolvió un error: ' . $e->getMessage());
}

linea();
linea('==========================================================');
linea(' Listo. El sistema quedó instalado.');
if ($usuarioAdmin !== null) {
    linea();
    linea(' Usuario:    ' . $usuarioAdmin);
    if ($claveGenerada) {
        linea(' Contraseña: ' . $claveAdmin);
        linea(' Anótala. Puedes cambiarla en Mi perfil > Cambiar contraseña.');
    } else {
        linea(' Contraseña: la que escribiste.');
    }
}
linea('==========================================================');
