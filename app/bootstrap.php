<?php
// Arranque común: toda página de public/ lo carga antes que nada.
// Lee la configuración, fija la zona horaria, carga las funciones e inicia la sesión.

define('RAIZ', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('PUBLIC_DIR', RAIZ . '/public');
define('VISTAS_DIR', APP_DIR . '/vistas');

$archivoConfig = RAIZ . '/config/config.php';
if (!is_file($archivoConfig)) {
    http_response_code(500);
    exit('Falta config/config.php. Copia config/config.example.php como config/config.php y completa los datos de la empresa.');
}
$config = require $archivoConfig;

date_default_timezone_set($config['zona_horaria']);

define('EMPRESA_SISTEMA', $config['empresa']['sistema']);
define('EMPRESA_LOGO', $config['empresa']['logo']);
define('EMPRESA_ICONO', $config['empresa']['icono'] ?? 'fas fa-store');
// Valores iniciales de los datos de la empresa y del ticket; los que se guarden desde el sistema los reemplazan (más abajo)
$datosIniciales = [
    'empresa.nombre'    => $config['empresa']['nombre'],
    'empresa.ruc'       => $config['empresa']['ruc'] ?? '',
    'empresa.direccion' => $config['empresa']['direccion'] ?? '',
    'empresa.telefono'  => $config['empresa']['telefono'] ?? '',
    'ticket.pie'        => $config['ticket']['pie'] ?? '¡Gracias por su compra!',
    'ticket.ancho_mm'   => $config['ticket']['ancho_mm'] ?? 80,
];
define('MONEDA', $config['moneda']);
define('ZONA_HORARIA_BD', $config['zona_horaria_bd']);
define('PASSWORD_PREDETERMINADA', $config['password_predeterminada']);
define('BASE_DATOS', $config['base_datos']);
define('TEMA', $config['tema']);
define('TIENDAS', $config['tiendas']);
define('UNIDADES', $config['unidades'] ?? ['und' => ['nombre' => 'Unidad', 'decimales' => false]]);
define('FORMAS_PAGO', $config['formas_pago'] ?? ['efectivo' => 'Efectivo']);
define('RESPALDOS', ($config['respaldos'] ?? []) + ['copia' => '', 'conservar' => 30]);
define('HOY', date('Y-m-d'));

unset($config, $archivoConfig);

// Con display_errors apagado, un error no atrapado (por ejemplo, MySQL rechaza la conexión) deja la página
// en blanco. Se registra el detalle en el log de PHP y se muestra un aviso entendible.
if (PHP_SAPI !== 'cli') {
    set_exception_handler(function (Throwable $e) {
        error_log((string) $e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        $mensaje = 'Ocurrió un error inesperado. El detalle quedó en el registro de errores de PHP.';
        if ($e instanceof PDOException && preg_match('/\[(1045|1049|2002)\]/', $e->getMessage(), $codigo)) {
            $mensaje = [
                '1045' => 'MySQL rechazó el usuario o la contraseña de la base de datos (config/config.php, sección base_datos).',
                '1049' => 'La base de datos indicada en config/config.php no existe. Créala e importa database/esquema.sql.',
                '2002' => 'No se pudo conectar con MySQL. Revisa que el servicio esté encendido y el host en config/config.php.',
            ][$codigo[1]];
        }
        echo '<div style="max-width: 640px; margin: 40px auto; padding: 16px 20px; border: 1px solid #f5c2c7; border-radius: 8px;'
           . ' background: #f8d7da; color: #842029; font-family: system-ui, sans-serif;">'
           . '<strong>No se pudo completar la operación.</strong><br>' . htmlspecialchars($mensaje) . '</div>';
    });
}

require_once APP_DIR . '/base_datos.php';
require_once APP_DIR . '/ayudantes.php';
require_once APP_DIR . '/funciones/usuarios.php';
require_once APP_DIR . '/funciones/clientes.php';
require_once APP_DIR . '/funciones/productos.php';
require_once APP_DIR . '/funciones/categorias.php';
require_once APP_DIR . '/funciones/ventas.php';
require_once APP_DIR . '/funciones/compras.php';
require_once APP_DIR . '/funciones/carrito.php';
require_once APP_DIR . '/funciones/tiendas.php';
require_once APP_DIR . '/funciones/excel.php';
require_once APP_DIR . '/funciones/pdf.php';
require_once APP_DIR . '/funciones/importacion.php';
require_once APP_DIR . '/funciones/caja.php';
require_once APP_DIR . '/funciones/ajustes.php';
require_once APP_DIR . '/funciones/respaldos.php';
require_once APP_DIR . '/funciones/empresa.php';

// Datos de la empresa y del ticket: los guardados en "Datos de la empresa" o, si no hay, los de config.php
$datosEmpresa = leerDatosEmpresaGuardados() + $datosIniciales;
define('EMPRESA_NOMBRE', $datosEmpresa['empresa.nombre']);
define('EMPRESA_RUC', $datosEmpresa['empresa.ruc']);
define('EMPRESA_DIRECCION', $datosEmpresa['empresa.direccion']);
define('EMPRESA_TELEFONO', $datosEmpresa['empresa.telefono']);
define('TICKET', ['ancho_mm' => (int) $datosEmpresa['ticket.ancho_mm'], 'pie' => $datosEmpresa['ticket.pie']]);
define('DATOS_EMPRESA_INICIALES', $datosIniciales);
unset($datosEmpresa, $datosIniciales);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}
