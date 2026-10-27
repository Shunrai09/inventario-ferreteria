<?php
// Ayudantes de navegación, sesión, tiendas, unidades y tema.

// Ruta relativa desde la página actual hasta $ruta dentro de public/.
// Así los enlaces funcionan igual en la raíz del dominio, en una subcarpeta o con `php -S`.
function url($ruta){
    static $prefijo = null;
    if($prefijo === null){
        $prefijo = '';
        $script = str_replace('\\', '/', (string) realpath(get_included_files()[0]));
        $publico = str_replace('\\', '/', (string) realpath(PUBLIC_DIR)) . '/';
        if(strpos($script, $publico) === 0){
            $prefijo = str_repeat('../', substr_count(substr($script, strlen($publico)), '/'));
        }
    }
    return $prefijo . $ruta;
}

function redirigir($ruta){
    header("Location: " . url($ruta));
    exit;
}

// Envía al login a quien no haya iniciado sesión (o cuyo usuario ya no existe).
function requerirSesion(){
    if(empty($_SESSION['usuario'])){
        redirigir("login.php");
    }
    if(rolActual() === ''){
        session_unset();
        redirigir("login.php");
    }
}

// Rol del usuario con sesión ('administrador', 'vendedor'), o '' si no hay sesión o el usuario ya no existe.
// Se lee de la base en cada petición para que un cambio de rol aplique de inmediato.
function rolActual(){
    static $rol = null;
    if($rol === null){
        $usuario = !empty($_SESSION['idUsuario']) ? obtenerUsuarioPorId($_SESSION['idUsuario']) : null;
        $rol = $usuario->rol ?? '';
    }
    return $rol;
}

function esAdministrador(){
    return rolActual() === 'administrador';
}

// Para las páginas solo de administradores: los demás vuelven al punto de venta con un aviso.
function requerirAdministrador(){
    requerirSesion();
    if(!esAdministrador()){
        $_SESSION['aviso_permiso'] = 'Esa sección es solo para administradores.';
        redirigir("ventas/index.php");
    }
}

// Mensaje para mostrar después de redirigir (una sola vez). $tipo: success, danger, warning o info.
function guardarMensaje($tipo, $texto){
    $_SESSION['mensaje_pagina'] = ['tipo' => $tipo, 'texto' => $texto];
}

function mostrarMensaje(){
    if(empty($_SESSION['mensaje_pagina'])) return;
    ['tipo' => $tipo, 'texto' => $texto] = $_SESSION['mensaje_pagina'];
    unset($_SESSION['mensaje_pagina']);
    $icono = ['success' => 'check-circle', 'danger' => 'exclamation-circle', 'warning' => 'exclamation-triangle'][$tipo] ?? 'info-circle';
    echo '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">'
       . '<i class="fa fa-' . $icono . '"></i> ' . htmlspecialchars($texto)
       . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
}

// ==================== TIENDAS ====================
// Los datos de cada tienda están en config/config.php ('tiendas').

function nombreTienda($clasificacion){
    return TIENDAS[$clasificacion]['nombre'] ?? (string) $clasificacion;
}

function cortoTienda($clasificacion){
    return TIENDAS[$clasificacion]['corto'] ?? '?';
}

function badgeTienda($clasificacion){
    return TIENDAS[$clasificacion]['badge'] ?? 'bg-secondary';
}

// Con una sola tienda las páginas ocultan selectores, filtros, etiquetas y estadísticas por tienda.
function hayVariasTiendas(){
    return count(TIENDAS) > 1;
}

// Clave de la primera tienda: la que reciben los productos cuando solo hay una.
function tiendaPrincipal(){
    return (string) array_key_first(TIENDAS);
}

// ==================== UNIDADES Y CANTIDADES ====================
// Las unidades están en config/config.php ('unidades'). La clave es lo que se guarda en productos.unidad.
// Las cantidades se guardan con 3 decimales; las unidades sin decimales solo aceptan enteros.

function nombreUnidad($unidad){
    return UNIDADES[$unidad]['nombre'] ?? (string) $unidad;
}

function unidadConDecimales($unidad){
    return !empty(UNIDADES[$unidad]['decimales']);
}

// 2.500 -> "2.5", 10.000 -> "10", 1250.000 -> "1,250"
function formatearCantidad($cantidad){
    return rtrim(rtrim(number_format((float) $cantidad, 3, '.', ','), '0'), '.');
}

function cantidadConUnidad($cantidad, $unidad){
    return formatearCantidad($cantidad) . ' ' . ($unidad ?: 'und');
}

// Atributos step y min para un <input type="number"> de cantidad.
function atributosCantidad($unidad){
    return unidadConDecimales($unidad) ? 'step="any" min="0.001"' : 'step="1" min="1"';
}

// Convierte lo que escribió el usuario (acepta coma decimal) en una cantidad válida para la unidad,
// o null si no es un número mayor que 0 o trae decimales en una unidad que no los admite.
function leerCantidad($valor, $unidad){
    $valor = str_replace(',', '.', trim((string) $valor));
    if(!is_numeric($valor)) return null;
    $cantidad = round((float) $valor, 3);
    if($cantidad <= 0) return null;
    if(!unidadConDecimales($unidad) && floor($cantidad) != $cantidad) return null;
    return $cantidad;
}

// ==================== TEMA ====================

// Variables CSS (--color-primario, --color-fondo-navbar, ...) a partir de config 'tema'.
function variablesTema(){
    $css = '';
    foreach(TEMA as $nombre => $valor){
        $css .= '--color-' . str_replace('_', '-', $nombre) . ': ' . htmlspecialchars($valor) . '; ';
    }
    return $css;
}
